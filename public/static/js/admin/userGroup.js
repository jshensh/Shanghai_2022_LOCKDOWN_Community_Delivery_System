$(function() {
    customPjax("#createUserGroup", "#mainContainer");

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
            var dom = $('<a role="button" class="btn btn-default btn-sm" href="/dashboard/user_group/permission/' + row["id"] + '.html">编辑用户组</a>');
            customPjax(dom, "#mainContainer");
            return dom;
        }
    });

    jsGrid.fields.editControl = editField;

    $("#userGroupDetail").jsGrid({
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
            title: "用户组名",
            type: "text",
            validate: "required",
            align: "center"
        }].concat((permission['dashboard_user_group'] & 4) ? [{
            type: "editControl"
        }] : []).concat((permission['dashboard_user_group'] & 8) ? [{
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
                    url: "/api/v1/admin/UserGroup",
                    data: filter
                }).done(function (data) {
                    $("#srvCount").text(data["itemsCount"]);
                });
            },

            deleteItem: function (item) {
                return $.ajax({
                    type: "DELETE",
                    url: "/api/v1/admin/UserGroup/" + item["id"]
                });
            }
        }
    });

    $("#pageSize").change(function () {
        $("#userGroupDetail").jsGrid("option", "pageSize", ~~$(this).val());
        $.cookie("pageSize", ~~$(this).val());
    });

    $("#pageSize").val([$.cookie("pageSize") || 20]);
});

var submitForm = function(formDom) {
    var fDom = $(formDom),
        nameDom = fDom.find('input[name="name"]'),
        submitButton = fDom.find('button[type="submit"]'),
        name = nameDom.val(),
        groupId = ~~(fDom.find('input[name="id"]').val()),
        action = '/api/v1/admin/UserGroup' + (groupId ? `/${groupId}` : '');

    if (!name) {
        toastr.error('用户名不能为空', '数据不合法');
        return false;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: groupId ? 'PUT' : 'POST',
        url: action,
        data: {
            "name": name
        }
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('更新成功');
        $('#editUserGroupModal').modal('hide');
        $("#userDetail").jsGrid("loadData");
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};