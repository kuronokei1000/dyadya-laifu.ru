function roverAmoCRMExportItems(gridId, profileId, useAgents) {
    if (!gridId) {
        return;
    }

    let gridObject = BX.Main.gridManager.getById(gridId);
    if (!gridObject) {
        return;
    }

    let control = document.getElementById('rover-ac__export-items_control');
    if (control.classList.contains('rover-ac__exporting')) {
        return;
    }

    control.classList.add('rover-ac__exporting');
    document.getElementsByClassName('main-grid-action-panel')[0].classList.add('main-grid-disable');
    BX.addCustomEvent('rover.amoCrm.eventHandler.afterHandleAll', function (data) {
        control.classList.remove('rover-ac__exporting');
        document.getElementsByClassName('main-grid-action-panel')[0].classList.remove('main-grid-disable');
    });

    document.getElementsByClassName('main-grid-action-panel')[0].classList.add();

    let forAll = gridObject.instance.getActionsPanel().getForAllCheckbox().checked,
        params = document.getElementById('rover-acr__params'),
        entitiesIds,
        eventType = params.dataset.eventType,
        sourceType = params.dataset.sourceType,
        sourceId = params.dataset.sourceId;

    if (forAll) {
        entitiesIds = JSON.parse(params.dataset.entitiesIds);
    } else {
        entitiesIds = gridObject.instance.rows.getSelectedIds();
    }

    runRoverAmoCrmEventHandler(
        eventType,
        entitiesIds,
        profileId,
        sourceType,
        sourceId,
        'progress-bar-container',
        useAgents,
        BX.message('rover_acr__js_export_title'),
        BX.message('rover_acr__js_initialization'),
        useAgents ? BX.message('rover_acr__js_added_to_queue') : BX.message('rover_acr__js_export_complete')
    );
}