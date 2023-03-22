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
            return $('<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#addSmsAmountModal" data-uid="' + row["id"] + '">增加短信额度</button>&nbsp;<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#editUserModal" data-uid="' + row["id"] + '" data-name="' + row["name"] + '" data-groupid="' + row["group_id"] + '" data-group="' + row["group"] + '" onclick="editUser();">编辑用户</button>');
        }
    });

    jsGrid.fields.editControl = editField;

    $("#userDetail").jsGrid({
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
            name: "name",
            title: "用户名",
            type: "text",
            validate: "required",
            align: "center"
        }, {
            name: "group",
            title: "用户组",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center"
        }, {
            name: "sms_amount",
            title: "短信余量",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center"
        }].concat((permission['dashboard_user'] & 4) ? [{
            type: "editControl"
        }] : []).concat((permission['dashboard_user'] & 8) ? [{
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
                    url: "/api/v1/admin/User",
                    data: filter
                }).done(function (data) {
                    $("#srvCount").text(data["itemsCount"]);
                });
            },

            deleteItem: function (item) {
                return $.ajax({
                    type: "DELETE",
                    url: "/api/v1/admin/User/" + item["id"]
                });
            }
        }
    });

    $("#pageSize").change(function () {
        $("#userDetail").jsGrid("option", "pageSize", ~~$(this).val());
        $.cookie("pageSize", ~~$(this).val());
    });

    $("#pageSize").val([$.cookie("pageSize") || 20]);

    $('#editUserModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var uid = button.data('uid'),
            name = button.data('name'),
            groupId = button.data('groupid'),
            group = button.data('group');
        $('#editUserForm input[name="id"]').val(uid || '');
        $('#editUserForm input[name="name"]').val(name || '');
        $('#editUserForm input[name="pwd"]').val('');
        $('#editUserForm select[name="group_id"]').empty();
        if (groupId !== void 0 && groupId) {
            $('#editUserForm select[name="group_id"]').append($('<option value="' + groupId + '" selected="selected">' + group + '</option>'));
        }
    });

    $('#addSmsAmountModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var uid = button.data('uid');
        $('#addSmsAmountForm input[name="id"]').val(uid || '');
    });
});

var submitForm = function(formDom) {
    var fDom = $(formDom),
        nameDom = fDom.find('input[name="name"]'),
        pwdDom = fDom.find('input[name="pwd"]'),
        submitButton = fDom.find('button[type="submit"]'),
        name = nameDom.val(),
        pwd = pwdDom.val(),
        uid = ~~(fDom.find('input[name="id"]').val()),
        groupId = ~~(fDom.find('select[name="group_id"]').val()),
        action = '/api/v1/admin/User' + (uid ? `/${uid}` : '');

    if (!name) {
        toastr.error('用户名不能为空', '数据不合法');
        return false;
    }

    if (!uid && !pwd) {
        toastr.error('新密码不能为空', '数据不合法');
        return false;
    }

    if (!groupId) {
        toastr.error('用户组不能为空', '数据不合法');
        return false;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: uid ? 'PUT' : 'POST',
        url: action,
        data: {
            "name": name,
            "pwd": pwd,
            "group_id": groupId
        }
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('更新成功');
        $('#editUserModal').modal('hide');
        $("#userDetail").jsGrid("loadData");
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};

var addSmsAmount = function(formDom) {
    var fDom = $(formDom),
        quantityDom = fDom.find('input[name="quantity"]'),
        submitButton = fDom.find('button[type="submit"]'),
        quantity = quantityDom.val(),
        uid = ~~(fDom.find('input[name="id"]').val()),
        action = `/api/v1/admin/User/${uid}/modifySmsAmount`;

    if (!quantity) {
        toastr.error('数量不能为空', '数据不合法');
        return false;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: uid ? 'PUT' : 'POST',
        url: action,
        data: {
            "quantity": quantity,
        }
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('更新成功');
        $('#addSmsAmountModal').modal('hide');
        $("#userDetail").jsGrid("loadData");
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};

var createNewUser = function() {
    $('#editPanelTitle').html('新建');
    $('#editUserForm input[name="pwd"]').attr('placeholder', '新密码');
    $('#editUserModal').modal('show');
};

var editUser = function() {
    $('#editPanelTitle').html('编辑');
    $('#editUserForm input[name="pwd"]').attr('placeholder', '无需修改请留空');
};

$("select[name='group_id']").select2({
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

function formatData (data) {
    if (data.loading) {
        return data.text;
    }

    return data.name;
}

function formatDataSelection (data) {
    return data.name || data.text;
}