var submitForm = function(formDom, action) {
    var fDom = $(formDom),
        submitButton = fDom.find('button[type="submit"]');

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: 'POST',
        url: action,
        data: new FormData(formDom),
        processData: false,
        contentType: false,
        dataType: 'text'
    }).then(function(data, status) {
        toastr.success('提交成功');
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
            $('#downloadTemplate').attr('href', `/api/v1/admin/SmsTemplate/${e.params.data.id}/download`).removeAttr('disabled');
            $('#preview').customVal(`【${smsSignName}】${e.params.data.content}`);
        });

        $("input[name='file']").on('change', function(e) {
            if ($(e.target).val()) {
                submitForm($('#smsForm')[0], '/api/v1/admin/Sms/preview2');
            }
        });
    }, 0);
});