function AmoCrmPresetList(sourcesList, connections) {
    return {
        sourcesList: sourcesList,
        connections: connections,
        lastPopup: null,
        popup: function (type) {

            if (this.sourcesList[type] === undefined) return;

            this.getPopup(type).show();
        },
        getPopupName: function (type) {
            return "rover_acpl__" + type;
        },
        getSelect: function (id, list, title, emptyNote) {
            var result = document.createElement("div");

            if (title.length) {
                result.innerHTML = '<p><b>' + title + '</b></p>';
            }

            if ((list === undefined)
                || (list === [])
                || (!Object.keys(list).length)) {
                var span = document.createElement("span");

                span.innerHTML = emptyNote;

                result.innerHTML = result.innerHTML + span.innerHTML;

                return result;
            }

            var select = document.createElement("select"),
                optionsRaw, keys = [], keyO;

            select.setAttribute('id', id);

            if (list === undefined) return select;

            optionsRaw = list;

            for (keyO in optionsRaw) {
                if (optionsRaw.hasOwnProperty(keyO)) {
                    keys.push(optionsRaw[keyO]);
                }
            }

            keys.sort().forEach(function (keyA) {
                for (keyO in optionsRaw) {
                    if (!optionsRaw.hasOwnProperty(keyO)) continue;

                    if (optionsRaw[keyO] != keyA) continue;

                    select.options[select.options.length] = new Option(optionsRaw[keyO], keyO);
                    break;
                }
            });

            result.append(select);

            return result;
        },
        getPopup: function (type) {
            var popup, content = document.createElement("div"), buttons = [];

            if (Object.keys(this.connections).length && Object.keys(this.sourcesList[type]).length) {
                buttons.push(
                    new BX.PopupWindowButton({
                        text: BX.message['rover_acpl__button_add'],
                        className: "popup-window-button-accept",
                        events: {
                            click: function () {
                                const profileSelect = BX('profile'),
                                    connectionSelect = BX('connection');

                                if ((profileSelect.value > 0) && (connectionSelect.value > 0)) {

                                    window.location =  BX.message('rover_acpl__profile_element_template')
                                        .replace('#UF_SOURCE_TYPE#', type)
                                        .replace('#UF_SOURCE_ID#', profileSelect.value)
                                        .replace('#UF_CONNECTION_ID#', connectionSelect.value);
                                }

                                this.popupWindow.close();
                            }
                        }
                    })
                );
            }

            buttons.push(new BX.PopupWindowButton({
                text: BX.message['rover_acpl__button_close'],
                className: "webform-button-link-cancel",
                events: {
                    click: function () {
                        this.popupWindow.close();
                    }
                }
            }));

            if (this.lastPopup !== null) {
                this.lastPopup.close();
            }

            content.append(this.getSelect('profile', this.sourcesList[type], BX.message['rover_acpl__' + type + '_select'], BX.message['rover_acpl__' + type + '_empty']));
            content.append(this.getSelect('connection', this.connections, BX.message['rover_acpl__connection_select'], BX.message['rover_acpl__connection_empty']));

            popup = new BX.PopupWindow(
                this.getPopupName(type),
                null,
                {
                    content: content,
                    closeIcon: {right: "20px", top: "10px"},
                    titleBar: {
                        content: BX.create("span", {
                            html: '<h2>' + BX.message['rover_acpl__title'] + '</h2>',
                            props: {className: 'access-title-bar'}
                        })
                    },
                    zIndex: 0,
                    offsetLeft: 0,
                    offsetTop: 0,
                    closeByEsc: true,
                    /* lightShadow : true,*/
                    draggable: {restrict: false},
                    buttons: buttons,
                    overlay: {backgroundColor: 'black', opacity: '80'},
                    events: {
                        onAfterPopupShow: function () {
                            if (isSelect2Enabled()) {
                                $('select#profile,select#connection').each(function () {
                                    initSelect2onElement($(this), '(по умолчанию)');
                                });
                            }
                        }
                    }
                });

            this.lastPopup = popup;

            return popup;
        }
    }

    /**
     *
     * @returns {boolean}
     */
    function isSelect2Enabled() {
        return (typeof jQuery !== 'undefined') && (typeof jQuery.fn.select2 != 'undefined');
    }
}