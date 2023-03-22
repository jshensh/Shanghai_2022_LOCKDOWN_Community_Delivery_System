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
            return $('<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#editGroupBuyModal" data-id="' + row["id"] + '" data-title="' + row["title"] + '" onclick="editGroupBuy();" style="margin: 2.5px 0">编辑团购</button>&nbsp;<a class="btn btn-default btn-sm" data-pjax href="/dashboard/order/index?groupBuyId=' + row["id"] + '&groupBuy=' + encodeURIComponent(row['title']) + '" role="button" style="margin: 2.5px 0">订单管理</a>&nbsp;<a class="btn btn-default btn-sm" href="/api/v1/admin/Order/export/?groupBuyId=' + row["id"] + '&mergeCell=true" role="button" style="margin: 2.5px 0">导出发货单</a>');
        }
    });

    jsGrid.fields.editControl = editField;

    $("#groupBuyDetail").jsGrid({
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
        //     name: "id",
        //     type: "firstCheckBox",
        //     align: "center",
        //     sorting: false,
        //     width: 30
        // }, {
            name: "id2",
            title: "ID",
            type: "id",
            align: "center",
            width: 30
        }, {
            name: "title",
            title: "标题",
            type: "text",
            validate: "required",
            align: "center"
        }, {
            name: "user",
            title: "创建用户",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center",
            width: 50
        }].concat((permission['dashboard_group_buy'] & 4) ? [{
            type: "editControl"
        }] : []).concat((permission['dashboard_group_buy'] & 8) ? [{
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

        onDataLoaded: function(grid, data) {
            console.log("#groupBuyDetail a[data-pjax]");
            customPjax("#groupBuyDetail a[data-pjax]", $(this).data("custom-pjax-render-to") || "#mainContainer");
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
                    url: "/api/v1/admin/GroupBuy",
                    data: filter
                }).done(function (data) {
                    $("#srvCount").text(data["itemsCount"]);
                });
            },

            deleteItem: function (item) {
                return $.ajax({
                    type: "DELETE",
                    url: "/api/v1/admin/GroupBuy/" + item["id"]
                });
            }
        }
    });

    $("#pageSize").change(function () {
        $("#groupBuyDetail").jsGrid("option", "pageSize", ~~$(this).val());
        $.cookie("pageSize", ~~$(this).val());
    });

    $("#pageSize").val([$.cookie("pageSize") || 20]);

    $('#editGroupBuyModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id'),
            title = button.data('title');
        $('#editGroupBuyForm input[name="id"]').val(id || '');
        $('#editGroupBuyForm input[name="title"]').val(title || '');
    });
});

var submitForm = function(formDom) {
    var fDom = $(formDom),
        titleDom = fDom.find('input[name="title"]'),
        submitButton = fDom.find('button[type="submit"]'),
        title = titleDom.val(),
        id = ~~(fDom.find('input[name="id"]').val()),
        action = '/api/v1/admin/GroupBuy' + (id ? `/${id}` : '');

    if (!title) {
        toastr.error('标题不能为空', '数据不合法');
        return false;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: id ? 'PUT' : 'POST',
        url: action,
        data: {
            "title": title
        }
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('更新成功');
        $('#editGroupBuyModal').modal('hide');
        $("#groupBuyDetail").jsGrid("loadData");
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};

var createNewGroupBuy = function() {
    $('#editPanelTitle').html('新建');
    $('#editGroupBuyForm input[name="pwd"]').attr('placeholder', '新密码');
    $('#editGroupBuyModal').modal('show');
};

var editGroupBuy = function() {
    $('#editPanelTitle').html('编辑');
    $('#editGroupBuyForm input[name="pwd"]').attr('placeholder', '无需修改请留空');
};