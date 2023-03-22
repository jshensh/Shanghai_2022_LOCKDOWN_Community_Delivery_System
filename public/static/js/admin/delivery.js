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

    var productField = function (config) {
        jsGrid.Field.call(this, config);
    };

    productField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            return value.length ? '<ul style="margin-bottom: 0; text-align: left;">' + value.map(function(v) { return `<li>${v.product} × ${v.quantity} 件</li>`; }).join('') + '</ul>' : '';
        },
    });

    jsGrid.fields.product = productField;

    var groupBuyField = function (config) {
        jsGrid.Field.call(this, config);
    };

    groupBuyField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            return value.length ? '<ul style="margin-bottom: 0; text-align: left;">' + value.map(function(v) { return `<li>${v}</li>`; }).join('') + '</ul>' : '';
        },
    });

    jsGrid.fields.groupBuy = groupBuyField;

    var statsField = function (config) {
        jsGrid.Field.call(this, config);
    };

    statsField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            return `${value['shipped_quantity']} / ${value['quantity']}`;
        },
    });

    jsGrid.fields.stats = statsField;

    var deliveryField = function (config) {
        jsGrid.Field.call(this, config);
    };

    deliveryField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            return value.length ? $.map(value, function(value) {
                return value['name']
            }).join('、') : '<i>未分配</i>';
        },
    });

    jsGrid.fields.delivery = deliveryField;

    var editField = function (config) {
        jsGrid.Field.call(this, config);
    };

    editField.prototype = new jsGrid.Field({
        title: '操作',
        sorting: false,
        align: "center",

        itemTemplate: function (value, row) {
            return $(((permission['dashboard_delivery'] & 4) ? '<p style="margin-bottom: 5px;"><button type="button" class="btn btn-default btn-sm" data-toggle="modal" data-target="#editDeliveryModal" data-id="' + row["id"] + '">编辑配送员</button></p>' : '') + `<a class="btn btn-default btn-sm" data-pjax href="/dashboard/delivery/detail/${row["id"]}.html" role="button">发货</a>`);
        }
    });

    jsGrid.fields.editControl = editField;

    $("#deliveryDetail").jsGrid({
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
            name: "group_buy",
            title: "团购",
            type: "groupBuy",
            validate: "required",
            align: "center",
            sorting: false,
            width: 240
        }, {
            name: "order_details",
            title: "商品",
            type: "product",
            validate: "required",
            align: "center",
            sorting: false,
            width: 280
        }, {
            name: "delivery",
            title: "配送员",
            type: "delivery",
            validate: "required",
            align: "center",
            sorting: false
        }, {
            name: "stats",
            title: "发货数量",
            type: "stats",
            validate: "required",
            align: "center",
            sorting: false,
        }, {
            name: "created_at",
            title: "创建时间",
            type: "text",
            validate: "required",
            align: "center",
            sorting: false,
            width: 100
        }, {
            type: "editControl"
        }],

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
            customPjax("#deliveryDetail a[data-pjax]", $(this).data("custom-pjax-render-to") || "#mainContainer");
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
                    url: "/api/v1/admin/Delivery",
                    data: filter
                }).done(function (data) {
                    $("#srvCount").text(data["itemsCount"]);
                });
            },

            deleteItem: function (item) {
                return $.ajax({
                    type: "DELETE",
                    url: "/api/v1/admin/Delivery/" + item["id"]
                });
            }
        }
    });

    $("#pageSize").change(function () {
        $("#deliveryDetail").jsGrid("option", "pageSize", ~~$(this).val());
        $.cookie("pageSize", ~~$(this).val());
    });

    $("#pageSize").val([$.cookie("pageSize") || 20]);

    $('#editDeliveryModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var id = button.data('id');
        $('#editDeliveryForm input[name="id"]').val(id || '');
        
        $.ajax({
            type: 'GET',
            url: '/api/v1/admin/Delivery/' + id,
        }).then(function(data, status) {
            if (data['status'] !== 'success') {
                toastr.error(data["error"] || '未知错误', "数据拉取失败");
                e.preventDefault();
                e.stopImmediatePropagation();
                return false;
            }
            $('#editDeliveryUser').empty();
            $('#editDeliveryUser').customVal(data['data']['delivery'].map(function(v) {
                return {'value': v['id'], 'text': v['name']};
            }));
        }).fail(function(data) {
            toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "数据拉取失败");
        });
    });

    $("select[id='groupBuy']").select2({
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
        placeholder: '搜索团购活动',
        closeOnSelect: false,
        tags: false,
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
                        group_buy_id: $("select[id='groupBuy']").customVal(), // search term
                    },
                    pageIndex: params.page,
                    pageSize: 50
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;

                return {
                    results: data.data.filter(function(item) {
                        return ~~item.unshipped_quantity > 0;
                    }).map(function(item) {
                        item.id = item.product;
                        return item;
                    }),
                    pagination: {
                        more: (params.page * 50) < data.itemsCount
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

            return data.product;
        },
        templateSelection: function (data) {
            return data.product || data.text;
        }
    });

    $("select[id='deliveryUser']").select2({
        ajax: {
            url: "/api/v1/admin/Delivery/getDeliveryUsers/",
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
        placeholder: ' 没有请留空',
        closeOnSelect: false,
        tags: false,
        escapeMarkup: function (markup) { return markup; },
        minimumInputLength: 0,
        templateResult: function (data) {
            if (data.loading) {
                return data.text;
            }

            return data.name;
        },
        templateSelection: function (data) {
            return data.name || data.text;
        }
    });

    $("select[id='editDeliveryUser']").select2({
        ajax: {
            url: "/api/v1/admin/Delivery/getDeliveryUsers/",
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
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
        placeholder: ' 没有请留空',
        closeOnSelect: false,
        tags: false,
        escapeMarkup: function (markup) { return markup; },
        minimumInputLength: 0,
        templateResult: function (data) {
            if (data.loading) {
                return data.text;
            }

            return data.name;
        },
        templateSelection: function (data) {
            return data.name || data.text;
        }
    });

    $("select[id='deliveryTime']").select2({
        minimumResultsForSearch: -1
    });

    $("select[id='groupBuy']").on('change', function(e) {
        clearInput('#product');
        $('#quantityContainer').empty();
    });

    $("select[id='product']").on('select2:select', function(e) {
        var data = e.params.data;
        $('#quantityContainer').append(inputDomRender('param', 'text', {'key': data['product'], 'value': data['unshipped_quantity']}, `商品“${data['product']}”已分配发货 ${data['shipped_quantity']} 件，未发货 ${data['unshipped_quantity']} 件`));
    });

    $("select[id='product']").on('select2:unselect', function(e) {
        var data = $(this).customVal();
        $('#' + $('#quantityContainer').find(`input[id$="_key"][value="${e.params.data['product']}"]`).attr('id').match(/^(param_.*)(?:_key)$/)[1] + '_delete').click();
    });

    $('#createDeliveryModal').on('show.bs.modal', function (e) {
        $('#deliveryAccordion').show();
        $('#deliveryConfirm').hide();
    });

    $('#createDeliveryModal').on('shown.bs.modal', function (e) {
        if ($('#collapseOne').css('height') === '0px') {
            $('a[href="#collapseOne"]').trigger('click');
        }
    });
});

var submitForm = function(formDom) {
    var fDom = $(formDom),
        groupBuyDom = fDom.find('select[id="groupBuy"]'),
        productDom = fDom.find('select[id="product"]'),
        quantityDom = $('#quantityContainer input[id$="_value"]'),
        submitButton = fDom.find('button[type="submit"]'),
        groupBuy = groupBuyDom.customVal(),
        product = productDom.customVal(),
        action = '/api/v1/admin/Delivery',
        confirmMsg = '';

    if (!groupBuy) {
        toastr.error('团购项目不能为空', '数据不合法');
        if ($('#collapseOne').css('height') === '0px') {
            $('a[href="#collapseOne"]').trigger('click');
        }
        return false;
    }

    confirmMsg += '<p><strong>团购</strong></p><ul>';
    var groupBuySelected = groupBuyDom.select2('data');
    for (var i = 0; i < groupBuySelected.length; i++) {
        confirmMsg += `<li>${groupBuySelected[i]['title']}</li>`;
    }
    confirmMsg += '</ul>';
    if (!product) {
        toastr.error('商品不能为空', '数据不合法');
        if ($('#collapseTwo').css('height') === '0px') {
            $('a[href="#collapseTwo"]').trigger('click');
        }
        return false;
    }

    confirmMsg += '<p><strong>商品</strong></p><ul>';
    for (var i = 0; i < quantityDom.length; i++) {
        var tmpQuantity = ~~$(quantityDom[i]).customVal();
        if (!tmpQuantity || tmpQuantity < 1) {
            toastr.error('数量不能为空或者小于 1', '数据不合法');
            if ($('#collapseThree').css('height') === '0px') {
                $('a[href="#collapseThree"]').trigger('click');
            }
            $(quantityDom[i]).focus();
            return false;
        }
        var quantityDomKey = $(quantityDom[i]).attr('id').match(/^(param_.*)(?:_value)$/)[1];
        confirmMsg += '<li>' + $('#quantityContainer').find(`input[id="${quantityDomKey}_key"]`).val() + ` <code>${tmpQuantity} 件</code></li>`;
    }
    confirmMsg += '</ul>';

    confirmMsg += '<p><strong>配送员</strong>：';
    var deliveryUser = fDom.find('select[id="deliveryUser"]').select2('data');
    if (!deliveryUser || !deliveryUser.length) {
        confirmMsg += '团长亲自配送';
    } else {
        for (var i = 0; i < deliveryUser.length; i++) {
            console.log(deliveryUser[i]);
            confirmMsg += `${deliveryUser[i]['name']} `;
        }
    }
    confirmMsg += '</p>';

    confirmMsg += '<p><strong>短信通知预估配送时间</strong>：' + (fDom.find('select[id="deliveryTime"]').select2('data'))[0]['text'] + '</p>';

    if ($('#deliveryConfirm').css('display') === 'none') {
        $('#deliveryAccordion').hide();
        $('#deliveryConfirmContent').html(confirmMsg);
        $('#deliveryConfirm').show();
        return;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: 'POST',
        url: action,
        data: fDom.serialize()
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success(data['success'], '发货任务创建成功');
        $('#createDeliveryModal').modal('hide');
        $("#deliveryDetail").jsGrid("loadData");
        clearInput('#groupBuy');
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "发货任务创建失败");
    });
};

var createNewDelivery = function() {
    $('#editPanelTitle').html('新建');
    $('#editDeliveryForm input[name="pwd"]').attr('placeholder', '新密码');
    $('#editDeliveryModal').modal('show');
};

var editDelivery = function(formDom) {
    var fDom = $(formDom),
        submitButton = fDom.find('button[type="submit"]');

    $.ajax({
        type: 'PUT',
        url: '/api/v1/admin/Delivery/' + $('#editDeliveryForm input[name="id"]').val(),
        data: $("#editDeliveryForm").serialize()
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success(data['success'], '配送员编辑成功');
        $('#editDeliveryModal').modal('hide');
        $("#deliveryDetail").jsGrid("loadData");
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "配送员编辑失败");
    });
};

$("select[name='group_id']").select2({
    ajax: {
        url: "/api/v1/admin/DeliveryGroup",
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

var uuid = function() {
    var temp_url = URL.createObjectURL(new Blob());
    var uuid = temp_url.toString();
    URL.revokeObjectURL(temp_url);
    return uuid.substr(uuid.lastIndexOf("/") + 1);
};

var inputDomRender = function(name, type) {
    var tmpId = uuid(),
        domName = name + '_' + tmpId,
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
            .attr('name', `product[${tmpId}][name]`)
            .css('height', '100%'),
        inputGroupAddonDom = $('<span>').attr('id', domName)
            .addClass('input-group-addon')
            .attr('autocomplete', 'off')
            .text(':'),
        deleteButtonDom = $('<a>').attr('role', 'button')
            .addClass('btn btn-default')
            .attr('href', '####')
            .attr('id', `${domName}_delete`)
            .html('<span class="glyphicon glyphicon-minus"></span>'),
        deleteButtonContainerDom = $('<span>').addClass('input-group-addon').css('border', 0).css('padding', 0).css('display', 'none'),
        descriptionDom = $('<p>').addClass('help-block'),
        valueInputDom = $('<input>').addClass('form-control')
            .attr('id', `${domName}_value`)
            .attr('type', 'number')
            .attr('placeholder', '数量')
            .attr('autocomplete', 'off')
            .val(value['value'])
            .attr('aria-describedby', domName)
            .attr('name', `product[${tmpId}][quantity]`);

    var renderDom = rowDom.append(
        contianerDom.append(
            inputGroupDom.append(
                keyInputDom
            ).append(
                inputGroupAddonDom
            ).append(
                valueInputDom
            ).append(
                deleteButtonContainerDom.append(
                    deleteButtonDom.click(function() {
                        renderDom.remove();
                    })
                )
            )
        ).append(
            description ? descriptionDom.html(description.replace(/\n/g, '<br />')).css('margin-bottom', '-10px') : ''
        )
    );

    return renderDom;
};

