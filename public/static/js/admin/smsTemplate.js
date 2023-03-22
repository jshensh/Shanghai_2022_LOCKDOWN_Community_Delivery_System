$(function() {
    var firstCheckBoxField = function (config) {
        jsGrid.Field.call(this, config);
    };

    firstCheckBoxField.prototype = new jsGrid.Field({
        headerTemplate: function () {
            return $("<input>").attr("type", "checkbox").attr("id", "checkall").click(function () {
                $('input[name="firstCheckbox[]"]').prop("checked", $(this).prop("checked"));
            });
        },

        itemTemplate: function (value) {
            return this._dom = $("<input>").attr("type", "checkbox").attr("name", "firstCheckbox[]").val(value).click(function () {
                var firstCheckBox = $('input[name="firstCheckbox[]"]'),
                    checkedFirstCheckBox = firstCheckBox.filter(":checked");
                $("#checkall").prop("checked", firstCheckBox.length === checkedFirstCheckBox.length && firstCheckBox.length);
            });
        },

        insertTemplate: function (value) {
            return;
        },

        editTemplate: function (value) {
            return;
        }
    });

    jsGrid.fields.firstCheckBox = firstCheckBoxField;

    var idField = function (config) {
        jsGrid.Field.call(this, config);
    };

    idField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            return row["id"];
        },

        insertTemplate: function (value) {
            return "";
        },

        editTemplate: function (value, row) {
            return row["id"];
        },

        insertValue: function () {
            return;
        },

        editValue: function () {
            return;
        }
    });

    jsGrid.fields.id = idField;

    var editField = function (config) {
        jsGrid.Field.call(this, config);
    };

    editField.prototype = new jsGrid.Field({
        title: '操作',
        sorting: false,
        align: "center",

        itemTemplate: function (value, row) {
            return $('<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#editSmsTemplateModal" data-id="' + row["id"] + '" onclick="editSmsTemplate();">编辑模板</button>');
        }
    });

    jsGrid.fields.editControl = editField;

    $("#smsTemplateDetail").jsGrid({
        width: "100%",

        inserting: false,
        editing: false,
        sorting: true,
        paging: true,
        pageLoading: true,
        invalidMessage: "格式错误，请检查后提交",
        autoload: true,

        pageSize: ~~$.cookie("pageSize") || 20,

        pagerContainer: $(".pagerContainer div:first"),

        fields: [{
            name: "id",
            type: "firstCheckBox",
            align: "center",
            sorting: false,
            width: 30
        }, {
            name: "id2",
            title: "ID",
            type: "id",
            align: "center",
            width: 30
        }, {
            name: "serial",
            title: "编号",
            type: "text",
            validate: "required",
            align: "center"
        }, {
            name: "name",
            title: "名称",
            type: "text",
            validate: "required",
            align: "center"
        }, {
            name: "content",
            title: "内容",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center"
        }, {
            name: "params",
            title: "参数列表",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center"
        }].concat((permission['dashboard_sms_template'] & 4) ? [{
            type: "editControl"
        }] : []).concat((permission['dashboard_sms_template'] & 8) ? [{
            sorting: false,
            type: "control",
            editButton: false
        }] : []),

        onError: function (args) {
            if (args["args"][0]["responseJSON"]) {
                toastr.error(args["args"][0]["responseJSON"]["error"], '操作失败', { timeOut: 3000 });
            } else {
                toastr.error('未知错误', '操作失败', { timeOut: 3000 });
            }
        },

        onItemInserted: function (args) {
            args["grid"]["_container"].jsGrid("loadData");
        },

        rowClick: function (args) {
            var checkbox = $(args["event"]["currentTarget"]).find('input[name="firstCheckbox[]"]');
            if ((!$(args["event"]["target"]).is('td') && !$(args["event"]["target"]).is('p') && !$(args["event"]["target"]).is('input')) || checkbox[0] === args["event"]["target"]) {
                return true;
            }
            return checkbox.click();
        },

        controller: {
            loadData: function (filter) {
                return $.ajax({
                    type: "GET",
                    url: "/api/v1/admin/SmsTemplate",
                    data: filter
                }).done(function (data) {
                    $("#srvCount").text(data["itemsCount"]);
                });
            },

            deleteItem: function (item) {
                return $.ajax({
                    type: "DELETE",
                    url: "/api/v1/admin/SmsTemplate/" + item["id"]
                });
            }
        }
    });

    $("#pageSize").change(function () {
        $("#smsTemlateDetail").jsGrid("option", "pageSize", ~~$(this).val());
        $.cookie("pageSize", ~~$(this).val());
    });

    $("#pageSize").val([$.cookie("pageSize") || 20]);

    $('#editSmsTemplateModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        $('#editSmsTemplateForm input[name="id"]').val(id || '');
        if (id) {
            $.ajax({
                type: 'GET',
                url: '/api/v1/admin/SmsTemplate/' + id,
            }).then(function(data, status) {
                console.log(data);
                if (data['status'] !== 'success') {
                    toastr.error(data["error"] || '未知错误', "数据拉取失败");
                    return false;
                }
                for (var i in data['data']) {
                    $(`#editSmsTemplateForm input[name="${i}"],textarea[name="${i}"]`).customVal(data['data'][i] || '');
                }
            }).fail(function(data) {
                toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "数据拉取失败");
            });
        } else {
            $(`#editSmsTemplateForm input[name="serial"]`).customVal('');
            $(`#editSmsTemplateForm input[name="name"]`).customVal('');
            $(`#editSmsTemplateForm textarea[name="content"]`).customVal('');
        }
    });
});

var submitForm = function(formDom) {
    var fDom = $(formDom),
        nameDom = fDom.find('input[name="name"]'),
        serialDom = fDom.find('input[name="serial"]'),
        contentDom = fDom.find('textarea[name="content"]'),
        paramsDom = fDom.find('textarea[name="params"]'),
        submitButton = fDom.find('button[type="submit"]'),
        name = nameDom.val(),
        content = contentDom.val(),
        params = paramsDom.val(),
        serial = ~~(serialDom.val()),
        id = ~~(fDom.find('input[name="id"]').val()),
        groupId = ~~(fDom.find('select[name="group_id"]').val()),
        action = '/api/v1/admin/SmsTemplate' + (id ? `/${id}` : '');

    if (!name) {
        toastr.error('名称不能为空', '数据不合法');
        return false;
    }

    if (!serial) {
        toastr.error('编号不能为空', '数据不合法');
        return false;
    }

    if (!content) {
        toastr.error('内容不能为空', '数据不合法');
        return false;
    }

    if (!params) {
        toastr.error('参数列表不能为空', '数据不合法');
        return false;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: id ? 'PUT' : 'POST',
        url: action,
        data: {
            "name": name,
            "serial": serial,
            "content": content,
            "params": params
        }
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('更新成功');
        $('#editSmsTemplateModal').modal('hide');
        $("#smsTemplateDetail").jsGrid("loadData");
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};

var createNewSmsTemplate = function() {
    $('#editPanelTitle').html('新建');
    $('#editSmsTemplateForm input[name="pwd"]').attr('placeholder', '新密码');
    $('#editSmsTemplateModal').modal('show');
};

var editSmsTemplate = function() {
    $('#editPanelTitle').html('编辑');
    $('#editSmsTemplateForm input[name="pwd"]').attr('placeholder', '无需修改请留空');
};

function formatData (data) {
    if (data.loading) {
        return data.text;
    }

    return data.name;
}

function formatDataSelection (data) {
    return data.name || data.text;
}