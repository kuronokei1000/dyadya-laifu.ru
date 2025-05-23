<?
class CAllcorp3Condition {
	const TYPE_CATALOG = 'D';
	const TYPE_PRODUCT = 'P';
	const TYPE_OFFERS = 'O';
	const TYPE_FULL = 'X';
	
	protected $arProductSelect;

	public function __get($name){
		if(property_exists($this, $name)){
			return $this->{$name};
		}

		return null;
	}

	public function CAllcorp3Condition(){
		$this->arProductSelect = array();
	}

	/**
	 * Return parsed conditions array.
	 *
	 * @param $condition
	 * @param $params
	 * @return array
	 */
	public function parseCondition($condition, $params)
	{
		$result = array();

		if (!empty($condition) && is_array($condition))
		{
			if ($condition['CLASS_ID'] === 'CondGroup')
			{
				if (!empty($condition['CHILDREN']))
				{
					foreach ($condition['CHILDREN'] as $child)
					{
						$childResult = $this->parseCondition($child, $params);

						// is group
						if ($child['CLASS_ID'] === 'CondGroup')
						{
							$result[] = $childResult;
						}
						// same property names not overrides each other
						elseif (isset($result[key($childResult)]))
						{
							$fieldName = key($childResult);

							if (!isset($result['LOGIC']))
							{
								if($fieldName == 0){
									$result[$fieldName] = array($fieldName => $result[$fieldName]);
									$result = array_merge(array(
										'LOGIC' => $condition['DATA']['All'],
									), $result);
								}
								else{
									$result = array(
										'LOGIC' => $condition['DATA']['All'],
										array($fieldName => $result[$fieldName])
									);
								}
							}

							$result[][$fieldName] = $childResult[$fieldName];
						}
						else
						{
							$result += $childResult;
						}
					}

					if (!empty($result))
					{
						$this->parsePropertyCondition($result, $condition, $params);

						if (count($result) > 1)
						{
							$result['LOGIC'] = $condition['DATA']['All'];
						}
					}
				}
			}
			else
			{
				$result += $this->parseConditionLevel($condition, $params);
			}
		}

		return $result;
	}

	protected function parseConditionLevel($condition, $params)
	{
		$result = array();

		if (!empty($condition) && is_array($condition))
		{
			$name = $this->parseConditionName($condition);
			if (!empty($name))
			{
				$operator = $this->parseConditionOperator($condition);
				$value = $this->parseConditionValue($condition, $name);
				$result[$operator.$name] = $value;

				if ($name === 'SECTION_ID')
				{
					if($operator === '!'){
						$result['!IBLOCK_SECTION_ID'] = $value;
						$result['!SUBSECTION'] = $value;
					}
					else{
						$result['INCLUDE_SUBSECTIONS'] = isset($params['INCLUDE_SUBSECTIONS']) && $params['INCLUDE_SUBSECTIONS'] === 'N' ? 'N' : 'Y';

						if (isset($params['INCLUDE_SUBSECTIONS']) && $params['INCLUDE_SUBSECTIONS'] === 'A')
						{
							$result['SECTION_GLOBAL_ACTIVE'] = 'Y';
						}
					}

					$result = array($result);
				}
			}
		}

		return $result;
	}

	protected function parseConditionName(array $condition)
	{
		$name = '';
		$conditionNameMap = array(
			'CondIBXmlID' => 'XML_ID',
			'CondIBActive' => 'ACTIVE',
			'CondIBSection' => 'SECTION_ID',
			'CondIBDateActiveFrom' => 'DATE_ACTIVE_FROM',
			'CondIBDateActiveTo' => 'DATE_ACTIVE_TO',
			'CondIBSort' => 'SORT',
			'CondIBDateCreate' => 'DATE_CREATE',
			'CondIBCreatedBy' => 'CREATED_BY',
			'CondIBTimestampX' => 'TIMESTAMP_X',
			'CondIBModifiedBy' => 'MODIFIED_BY',
			'CondIBTags' => 'TAGS',
			'CondCatQuantity' => 'CATALOG_QUANTITY',
			'CondCatWeight' => 'CATALOG_WEIGHT',
			'CondIBName' => 'NAME',
			'CondIBElement' => 'ID',
			'CondIBIBlock' => 'IBLOCK_ID',
			'CondIBCode' => 'CODE',
		);

		if (isset($conditionNameMap[$condition['CLASS_ID']]))
		{
			$name = $conditionNameMap[$condition['CLASS_ID']];
		}
		elseif (strpos($condition['CLASS_ID'], 'CondIBProp') !== false)
		{
			$name = $condition['CLASS_ID'];
		}
		elseif (strpos($condition['CLASS_ID'], 'CondCrossIBField') !== false)
		{
			$name = $condition['CLASS_ID'];
		}
		elseif (strpos($condition['CLASS_ID'], 'CondCrossIBProp') !== false)
		{
			$name = $condition['CLASS_ID'];
		}

		return $name;
	}

	protected function parseConditionOperator($condition)
	{
		$operator = '';

		switch ($condition['DATA']['logic'])
		{
			case 'Equal':
			case 'csEqual':
			case 'csIn':
				$operator = '';
				break;
			case 'Not':
			case 'csNotEqual':
			case 'csNotIn':
				$operator = '!';
				break;
			case 'Contain':
				$operator = '%';
				break;
			case 'NotCont':
				$operator = '!%';
				break;
			case 'Great':
				$operator = '>';
				break;
			case 'Less':
				$operator = '<';
				break;
			case 'EqGr':
				$operator = '>=';
				break;
			case 'EqLs':
				$operator = '<=';
				break;
			case 'csSIn':
				$operator = '=';
				break;
		}

		return $operator;
	}

	protected function parseConditionValue($condition, $name)
	{
		$value = $condition['DATA']['value'];

		switch ($name)
		{
			case 'DATE_ACTIVE_FROM':
			case 'DATE_ACTIVE_TO':
			case 'DATE_CREATE':
			case 'TIMESTAMP_X':
				$value = ConvertTimeStamp($value, 'FULL');
				break;
		}

		return $value;
	}

	protected function parsePropertyCondition(array &$result, array $condition, $params)
	{
		if (!empty($result))
		{
			$subFilter = array();

			foreach ($result as $name => $value)
			{
				if (!empty($result[$name]) && is_array($result[$name]))
				{
					$this->parsePropertyCondition($result[$name], $condition, $params);
				}
				else
				{
					if (($ind = strpos($name, 'CondIBProp')) !== false)
					{
						list($prefix, $iblock, $propertyId) = explode(':', $name);
						$operator = $ind > 0 ? substr($prefix, 0, $ind) : '';

						$catalogInfo = self::GetInfoByIBlock($iblockId);						
						if (!empty($catalogInfo))
						{
							if (
								$catalogInfo['CATALOG_TYPE'] != self::TYPE_CATALOG
								&& $catalogInfo['IBLOCK_ID'] == $iblock
							)
							{
								$subFilter[$operator.'PROPERTY_'.$propertyId] = $value;
							}
							else
							{
								$result[$operator.'PROPERTY_'.$propertyId] = $value;
							}
						}

						unset($result[$name]);
					}
					elseif (($ind = strpos($name, 'CondCrossIBField')) !== false){
						list($prefix, $iblock, $field) = explode(':', $name);
						$operator = $ind > 0 ? substr($prefix, 0, $ind) : '';

						$this->arProductSelect[] = $value === 'PARENT_IBLOCK_SECTION_ID' ? 'IBLOCK_SECTION_ID' : $value;

						$catalogInfo = self::GetInfoByIBlock($iblockId);
						if (!empty($catalogInfo))
						{
							if (
								$catalogInfo['CATALOG_TYPE'] != self::TYPE_CATALOG
								&& $catalogInfo['IBLOCK_ID'] == $iblock
							)
							{
								$subFilter[$operator.$field] = 'CondCrossIBField:'.$iblock.':'.$value;
							}
							else
							{
								$result[$operator.$field] = 'CondCrossIBField:'.$iblock.':'.$value;
							}
						}

						unset($result[$name]);
					}
					elseif (($ind = strpos($name, 'CondCrossIBProp')) !== false){
						list($prefix, $iblock, $propertyId) = explode(':', $name);
						$operator = $ind > 0 ? substr($prefix, 0, $ind) : '';

						$this->arProductSelect[] = 'PROPERTY_'.$value;

						$catalogInfo = self::GetInfoByIBlock($iblockId);
						if (!empty($catalogInfo))
						{
							if (
								$catalogInfo['CATALOG_TYPE'] != self::TYPE_CATALOG
								&& $catalogInfo['IBLOCK_ID'] == $iblock
							)
							{
								$subFilter[$operator.'PROPERTY_'.$propertyId] = 'CondCrossIBProp:'.$iblock.':'.$value;
							}
							else
							{
								$result[$operator.'PROPERTY_'.$propertyId] = 'CondCrossIBProp:'.$iblock.':'.$value;
							}
						}

						unset($result[$name]);
					}
				}
			}

			if (!empty($subFilter) && !empty($catalogInfo))
			{
				$offerPropFilter = array(
					'IBLOCK_ID' => $catalogInfo['IBLOCK_ID'],
					'ACTIVE_DATE' => 'Y',
					'ACTIVE' => 'Y'
				);

				if ($params['HIDE_NOT_AVAILABLE_OFFERS'] === 'Y')
				{
					$offerPropFilter['HIDE_NOT_AVAILABLE'] = 'Y';
				}
				elseif ($params['HIDE_NOT_AVAILABLE_OFFERS'] === 'L')
				{
					$offerPropFilter[] = array(
						'LOGIC' => 'OR',
						'CATALOG_AVAILABLE' => 'Y',
						'CATALOG_SUBSCRIBE' => 'Y'
					);
				}

				if (count($subFilter) > 1)
				{
					$subFilter['LOGIC'] = $condition['DATA']['All'];
					$subFilter = array($subFilter);
				}

				$result['=ID'] = \CIBlockElement::SubQuery(
					'PROPERTY_'.$catalogInfo['SKU_PROPERTY_ID'],
					$offerPropFilter + $subFilter
				);
			}
		}
	}

	public function GetInfoByIBlock($iblockId){
		if(\Bitrix\Main\Loader::includeModule('catalog')){
			$catalogInfo = \CCatalogSku::GetInfoByIBlock($iblock);
		}
		else{
			$catalogInfo = array(
				'IBLOCK_ID' => (int)$iblock,
				'CATALOG_TYPE' => self::TYPE_CATALOG,
			);
		}

		return $catalogInfo;
	}
}