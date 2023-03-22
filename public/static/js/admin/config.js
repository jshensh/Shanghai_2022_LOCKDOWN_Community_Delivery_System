function formatData (data) {
    if (data.loading) {
        return data.text;
    }

    return data.name;
}

function formatDataSelection (data) {
    return data.name || data.text;
}

var submitForm = function(formDom) {
    var fDom = $(formDom),
        submitButton = fDom.find('button[type="submit"]'),
        action = '/api/v1/admin/Config/update';

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: 'POST',
        url: action,
        data: fDom.serialize()
    }).then(function(data, status) {
        toastr.success('更新成功');
        submitButton.removeAttr('disabled');
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};

$(function() {
    $("select[name='sys_user_register_group']").select2({
        ajax: {
            url: "/api/v1/admin/UserGroup",
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
        placeholder: '搜索一个用户组',
        escapeMarkup: function (markup) { return markup; },
        minimumInputLength: 0,
        templateResult: formatData,
        templateSelection: formatDataSelection
    });
    
    $.ajax({
        type: 'GET',
        url: '/api/v1/admin/Config',
    }).then(function(data, status) {
        for (var i in data['data']) {
            $(`#${i}`).customVal(data['data'][i]);
        }
    });
});