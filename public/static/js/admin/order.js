$(function() {
    typeof window.gridFilter !== 'undefined' && delete window.gridFilter;
    
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

    var receiverInfoField = function (config) {
        jsGrid.Field.call(this, config);
    };

    receiverInfoField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            return `${row["building"]}-${row["room"]}, ${row["receiver"]},<br />${row["phone"]}`;
        },
    });

    jsGrid.fields.receiverInfo = receiverInfoField;

    var orderStatusField = function (config) {
        jsGrid.Field.call(this, config);
    };

    orderStatusField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            switch (row['status']) {
                case 1:
                    return row['delivery_id'] ? '已发货' : '待发货';
                case 2:
                    return '已核销';
                default:
                    return '其他';
            }
        },
    });

    jsGrid.fields.orderStatus = orderStatusField;

    var editField = function (config) {
        jsGrid.Field.call(this, config);
    };

    editField.prototype = new jsGrid.Field({
        title: '操作',
        sorting: false,
        align: "center",

        itemTemplate: function (value, row) {
            return $('<button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#editOrderModal" data-id="' + row["id"] + '" onclick="editOrder();">编辑</button>');
        }
    });

    jsGrid.fields.editControl = editField;

    $("#orderDetail").jsGrid({
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
            name: "serial",
            title: "单号",
            type: "text",
            validate: "required",
            align: "center",
            width: 30
        }, {
            name: "product",
            title: "商品名",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center"
        }, {
            name: "quantity",
            title: "数量",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center",
            width: 30
        }, {
            name: "receiver_info",
            title: "收件信息",
            type: "receiverInfo",
            validate: "required",
            sorting: false,
            align: "center"
        }, {
            name: "order_status",
            title: "订单状态",
            type: "orderStatus",
            validate: "required",
            sorting: false,
            align: "center"
        }, {
            name: "remark",
            title: "备注",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center"
        }, {
            name: "created_at",
            title: "支付时间",
            type: "text",
            validate: "required",
            align: "center",
            width: 80
        }, {
            name: "writeoff_at",
            title: "核销时间",
            type: "text",
            validate: "required",
            align: "center",
            width: 80
        }].concat((permission['dashboard_order'] & 4) ? [{
            type: "editControl"
        }] : []).concat((permission['dashboard_order'] & 8) ? [{
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
                if (typeof window.gridFilter !== "undefined") {
                    filter["filter"] = window.gridFilter;
                }
                return $.ajax({
                    type: "GET",
                    url: "/api/v1/admin/Order",
                    data: filter
                }).done(function (data) {
                    $("#srvCount").text(data["itemsCount"]);
                });
            },

            deleteItem: function (item) {
                return $.ajax({
                    type: "DELETE",
                    url: "/api/v1/admin/Order/" + item["id"]
                });
            }
        }
    });

    $("#pageSize").change(function () {
        $("#orderDetail").jsGrid("option", "pageSize", ~~$(this).val());
        $.cookie("pageSize", ~~$(this).val());
    });

    $("#pageSize").val([$.cookie("pageSize") || 20]);

    $('#editOrderModal').on('show.bs.modal', function (e) {
        var button = $(e.relatedTarget);
        var id = button.data('id');

        var selectedGroupBuy = $('#group_buy_id').select2('data');
        if (!selectedGroupBuy.length && !id) {
            toastr.error('录入订单前需要先筛选团购活动', "录入失败");
            e.preventDefault();
            e.stopImmediatePropagation();
            return false;
        }

        $('#editOrderForm input[name="id"]').val(id || '');
        if (id) {
            $.ajax({
                type: 'GET',
                url: '/api/v1/admin/Order/' + id,
            }).then(function(data, status) {
                if (data['status'] !== 'success') {
                    toastr.error(data["error"] || '未知错误', "数据拉取失败");
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    return false;
                }
                for (var i in data['data']) {
                    $(`#editOrderForm input[name="${i}"],textarea[name="${i}"]`).customVal(data['data'][i] || '');
                }
            }).fail(function(data) {
                toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "数据拉取失败");
            });
        } else {
            $(`#editOrderForm input[name="group_buy_id"]`).customVal(selectedGroupBuy[0]['id']);
            $(`#editOrderForm input[name="serial"]`).customVal('');
            $(`#editOrderForm input[name="product"]`).customVal('');
            $(`#editOrderForm textarea[name="quantity"]`).customVal('');
            $(`#editOrderForm textarea[name="receiver"]`).customVal('');
            $(`#editOrderForm textarea[name="phone"]`).customVal('');
            $(`#editOrderForm textarea[name="building"]`).customVal('');
            $(`#editOrderForm textarea[name="room"]`).customVal('');
            $(`#editOrderForm textarea[name="remark"]`).customVal('');
        }
    });

    setTimeout(function() {
        var groupBuyId = getQueryString('groupBuyId'), groupBuy = getQueryString('groupBuy');
        if (groupBuyId && groupBuy) {
            $("select[name='group_buy_id']").customVal([{'value': groupBuyId, 'text': groupBuy}]);
            $('#orderSearchButton').click();
        }
    }, 0);
});

var submitForm = function(formDom) {
    var fDom = $(formDom),
        submitButton = fDom.find('button[type="submit"]'),
        id = ~~(fDom.find('input[name="id"]').val()),
        action = '/api/v1/admin/Order' + (id ? `/${id}` : ''),
        requiredField = ['serial', 'product', 'quantity', 'receiver', 'phone', 'building'];

    for (var i = 0; i < requiredField.length; i++) {
        var field = fDom.find(`input[name="${requiredField[i]}"]`);
        if (!field.val()) {
            toastr.error(field.attr('placeholder') + '不能为空', '数据不合法');
            field.focus();
            return false;
        }
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: id ? 'PUT' : 'POST',
        url: action,
        data: fDom.serialize()
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('更新成功');
        $('#editOrderModal').modal('hide');
        $("#orderDetail").jsGrid("loadData");
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};

var importOrder = function(formDom) {
    var fDom = $(formDom),
        excelDom = fDom.find('input[name="excel"]'),
        submitButton = fDom.find('button[type="submit"]'),
        excel = excelDom.val(),
        id = ~~(fDom.find('input[name="id"]').val()),
        action = '/api/v1/admin/Order/import';

    if (!excel) {
        toastr.error('Excel 文件不能为空', '数据不合法');
        return false;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: 'POST',
        url: action,
        cache: false,
        data: new FormData(formDom),
        processData: false,
        contentType: false
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('导入成功');
        excelDom.val('');
        $('#importOrderModal').modal('hide');
        clearInput('#product', '#serial', '#phone', '#building');
        $('#orderSearchButton').click();
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        excelDom.val('');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误，请刷新页面后重试' : (data["responseJSON"]["error"] || '未知错误'), "导入失败");
    });
};

var createNewOrder = function() {
    $('#editPanelTitle').html('录入');
    $('#editOrderForm input[name="pwd"]').attr('placeholder', '新密码');
    $('#editOrderModal').modal('show');
};

var editOrder = function() {
    $('#editPanelTitle').html('编辑');
    $('#editOrderForm input[name="pwd"]').attr('placeholder', '无需修改请留空');
};

var exportOrder = function() {
    var selectedGroupBuy = $('#group_buy_id').select2('data');
    if (!selectedGroupBuy.length) {
        toastr.error('导出发货单前需要先筛选团购活动', "导出失败");
        return false;
    }
    var mergeCell = !(typeof arguments[0] !== 'undefined' && arguments[0]);

    var data = $('#product').val(), formData = new FormData;
    for (var i in data) {
        formData.append('filter[product][]', data[i]);
    }
    formData.append('groupBuyId', $("select[name='group_buy_id']").val());
    mergeCell && formData.append('mergeCell', 'true');
    formData.append('_t', new Date().getTime());

    window.open('/api/v1/admin/Order/export/?' + new URLSearchParams(formData).toString(), '_blank');
};

$("select[name='group_buy_id']").select2({
    ajax: {
        url: "/api/v1/admin/GroupBuy",
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                filter: {
                    title: params.term, // search term
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
    placeholder: '搜索一个团购活动',
    escapeMarkup: function (markup) { return markup; },
    minimumInputLength: 0,
    templateResult: function (data) {
        if (data.loading) {
            return data.text;
        }

        return data.title;
    },
    templateSelection: function (data) {
        return data.title || data.text;
    }
});

$("select[id='product']").select2({
    ajax: {
        url: "/api/v1/admin/Order/productList/",
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                filter: {
                    group_buy_id: $("select[name='group_buy_id']").customVal(), // search term
                },
                pageIndex: params.page
            };
        },
        processResults: function (data, params) {
            params.page = params.page || 1;

            return {
                results: data.data.map(function(item) {
                    return {
                        id: item.product,
                        product: item.product,
                        shipped_quantity: item.shipped_quantity,
                        unshipped_quantity: item.unshipped_quantity,
                    };
                }),
                pagination: {
                    more: (params.page * 20) < data.itemsCount
                }
            };
        }
    },
    placeholder: ' 选择商品',
    closeOnSelect: false,
    tags: false,
    escapeMarkup: function (markup) { return markup; },
    minimumInputLength: 0,
    templateResult: function (data) {
        if (data.loading) {
            return data.text;
        }

        return `${data.product}（已分配发货：${data.shipped_quantity}，未发货：${~~data.unshipped_quantity ? '<span style="color: red; font-weight: bold;">' + data.unshipped_quantity + '</span>' : '0'}）`;
    },
    templateSelection: function (data) {
        return data.product || data.text;
    }
});

$('#importOrderModal').on('show.bs.modal', function (e) {
    var selectedGroupBuy = $('#group_buy_id').select2('data');
    if (!selectedGroupBuy.length) {
        toastr.error('导入订单前需要先筛选团购活动', "导入失败");
        e.preventDefault();
        e.stopImmediatePropagation();
        return false;
    }
    $('#importOrderModal input[name="groupBuyId"]').val(selectedGroupBuy[0]['id']);
    $('#groupBuyImportOrdersWarning').html(selectedGroupBuy[0]['title'] ? `#${selectedGroupBuy[0]['id']} - ${selectedGroupBuy[0]['title']}` : `#${selectedGroupBuy[0]['id']} - ${selectedGroupBuy[0]['text']}`);
});

$("select[name='group_buy_id']").on('select2:select', function(e) {
    clearInput('#product', '#serial', '#phone', '#building');
});