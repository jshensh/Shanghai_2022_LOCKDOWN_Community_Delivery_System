var submitForm = function(formDom) {
    var fDom = $(formDom),
        submitButton = fDom.find('button[type="submit"]'),
        action = '/api/v1/admin/Sms/send';

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: 'POST',
        url: action,
        data: fDom.serialize(),
        dataType: 'text'
    }).then(function(data, status) {
        toastr.success('发送成功');
        responseBodyCodeEditor.setValue(data);
        submitButton.removeAttr('disabled');
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        responseBodyCodeEditor.setValue(data.responseText);
    });
};

function formatData (data) {
    if (data.loading) {
        return data.text;
    }

    return `${data.serial} - ${data.name}`;
}

function formatDataSelection (data) {
    return typeof data.name === 'undefined' ? data.text : `${data.serial} - ${data.name}`;
}

var uuid = function() {
    var temp_url = URL.createObjectURL(new Blob());
    var uuid = temp_url.toString();
    URL.revokeObjectURL(temp_url);
    return uuid.substr(uuid.lastIndexOf("/") + 1);
};

var getParams = function() {
    var paramKeyInputs = $('#paramsContainer').find('input[id$="_key"]'), re = {};

    for (var i = 0; i < paramKeyInputs.length; i++) {
        var uuid = paramKeyInputs[i].id.match(/^(header|param)(?:_)(.*)(?:_(key|value))$/);
        var tmpDom = $('#paramsContainer').find(`input[id$="${uuid[2]}_value"],textarea[id$="${uuid[2]}_value"]`).attr('name', paramKeyInputs[i].value);
        re[paramKeyInputs[i].value] = tmpDom.val();
    }

    return re;
}

var updatePreview = function() {
    var content = $('#originContent').val(), params = getParams();

    for (var i in params) {
        if (params[i]) {
            var tmp = ~~((i.match(/\d+/))[0]) + 1;
            content = content.replace(new RegExp(`\\{${tmp}\\}`, 'g'), params[i]);
        }
    }
    $('#preview').val(content);
};

var inputDomRender = function(name, type) {
    var domName = name + '_' + uuid(),
        value = typeof arguments[2] === 'undefined' ? {'key': '', 'value': ''} : arguments[2],
        description = typeof arguments[3] === 'undefined' ? null : arguments[3];

    var rowDom = $('<div>').addClass('row').css('margin-bottom', '15px'),
        contianerDom = $('<div>').addClass('col-xs-12'),
        inputGroupDom = $('<div>').addClass('input-group').css('height', '100%'),
        keyInputDom = $('<input>').addClass('form-control')
            .attr('id', `${domName}_key`)
            .attr('type', 'text')
            .attr('placeholder', 'Key')
            .attr('value', value['key'])
            .attr('aria-describedby', domName)
            .attr('readonly', 'readonly')
            .css('height', '100%'),
        inputGroupAddonDom = $('<span>').attr('id', domName)
            .addClass('input-group-addon')
            .attr('autocomplete', 'off')
            .text(':');

    switch (type) {
        case 'file':
            var valueInputDom = $('<input>').addClass('form-control')
                .attr('id', `${domName}_value`)
                .attr('type', 'file')
                .attr('aria-describedby', domName);
            break;
        case 'textarea':
            var valueInputDom = $('<textarea>').addClass('form-control')
                .attr('id', `${domName}_value`)
                .attr('type', 'file')
                .attr('aria-describedby', domName)
                .attr('rows', '5')
                .val(value['value']);
            break;
        default:
            var valueInputDom = $('<input>').addClass('form-control')
                .attr('id', `${domName}_value`)
                .attr('type', 'text')
                .attr('placeholder', 'Value')
                .attr('autocomplete', 'off')
                .val(value['value'])
                .attr('aria-describedby', domName);
    }

    valueInputDom = valueInputDom.on('input', updatePreview).attr('tabindex', '1');

    var renderDom = rowDom.append(
        contianerDom.append(
            inputGroupDom.append(
                keyInputDom
            ).append(
                inputGroupAddonDom
            ).append(
                valueInputDom
            )
        )
    );

    return renderDom;
};

$(function() {
    setTimeout(function() {
        responseBodyCodeEditor = CodeMirror.fromTextArea(document.getElementById('responseBody'), {
            mode: "javascript",
            theme: "github-light",
            matchBrackets: true,
            lineNumbers: true,
            indentUnit: 4,
            scrollbarStyle: "simple",
            lineWrapping: true
        });

        phoneCodeEditor = CodeMirror.fromTextArea(document.getElementById('phones'), {
            mode: "text",
            theme: "github-light",
            lineNumbers: true,
            scrollbarStyle: "simple",
        }).on('change', editor => {
            $('#phones').val(editor.getValue());
        });

        $("select[name='template']").select2({
            ajax: {
                url: "/api/v1/admin/SmsTemplate",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        filter: {
                            name: params.term, // search term
                        },
                        pageIndex: params.page
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;

                    return {
                        results: data.data,
                        pagination: {
                            more: (params.page * 20) < data.itemsCount
                        }
                    };
                }
            },
            placeholder: '搜索一个模板',
            escapeMarkup: function (markup) { return markup; },
            minimumInputLength: 0,
            templateResult: formatData,
            templateSelection: formatDataSelection
        });

        $("select[name='template']").on('select2:select', function(e) {
            $('#paramsContainer').empty();
            $('#preview').customVal(`【${smsSignName}】${e.params.data.content}`);
            $('#originContent').customVal(`【${smsSignName}】${e.params.data.content}`);

            var paramLength = e.params.data.content.match(/\{\d+\}/g).length;

            for (var i = 0; i < paramLength; i++) {
                $('#paramsContainer').append(inputDomRender('param', 'text', {'key': `params[${i}]`, 'value': ''}));
            }
        });
    }, 0);
});