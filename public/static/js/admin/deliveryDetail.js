var submitForm = function(formDom) {
    var fDom = $(formDom),
        submitButton = fDom.find('button[type="submit"]'),
        action = `/api/v1/admin/Delivery/${deliveryId}/writeOff`;

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: 'POST',
        url: action,
        data: fDom.serialize()
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success('核销成功');
        $('#writeOffModal').modal('hide');
        refreshdeliveryOrderDetails();
        refreshBuildingSelect2();
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "核销失败");
    });
};

var submitCalcSummaryTable = function() {
    var fDom = $('#calcSummaryTableForm'),
        submitButton = fDom.find('button[type="submit"]'),
        action = `/api/v1/admin/Delivery/${deliveryId}/getSummaryTable`;

    submitButton.attr('disabled', 'disabled');
    $('#summaryTable').empty();

    $.ajax({
        type: 'POST',
        url: action,
        data: fDom.serialize()
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        var lastBuilding = '', cacheRow = [];
        data = data['data'];
        for (var i = 0; i < data.length + 1; i++) {
            if (i > 0 && (i === data.length || lastBuilding !== data[i]['building'])) {
                $('#summaryTable').append(`<h4>${lastBuilding}</h4><ul>${cacheRow.join('')}</ul>`);
            }
            if (i < data.length) {
                if (lastBuilding !== data[i]['building']) {
                    lastBuilding = data[i]['building'];
                    cacheRow = [];
                }
                cacheRow.push(`<li>${data[i]['product']}&nbsp;&nbsp;<code>${data[i]['quantity']} 件</code></li>`);
            }
        }
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error('楼栋汇总表拉取失败', '网络错误', { timeOut: 3000 });
    });
};

var submitClaimBuilding = function() {
    var fDom = $('#claimBuildingForm'),
        submitButton = fDom.find('button[type="submit"]'),
        action = `/api/v1/admin/Delivery/${deliveryId}/claimBuilding`;

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: 'POST',
        url: action,
        data: fDom.serialize()
    }).then(function(data, status) {
        submitButton.removeAttr('disabled');
        toastr.success(data['success'], '楼栋认领成功');
        $('#claimBuildingModal').modal('hide');
        $('#claimBuilding').hide();
        refreshBuildingSelect2();
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(data['responseJSON']['error'] || '未知错误', '楼栋认领失败', { timeOut: 3000 });
    });
};

var refreshBuildingSelect2 = function() {
    doAjaxPromise(`/api/v1/admin/Delivery/${deliveryId}/getBuilding?pageSize=100`, 'get', {})
        .then(function(xhr) {
            var tempVal = $("#building").customVal(), data = xhr.response.data;
            for (var i = 0; i < data.length; i++) {
                if (data[i]['building'] === tempVal) {
                    $("#building").empty().trigger('change:select2');
                    $("#building").customVal([{'value': tempVal, 'text': `${data[i]['building']} - 配送员：${data[i]['delivery_user'] ?? '无'}${data[i]['is_pickup'] ? '（自提）' : ''}，待发货 ${~~data[i]['not_shipped_quantity'] ? '<span style="color: red; font-weight: bold;">' + data[i]['not_shipped_quantity'] + '</span>' : data[i]['not_shipped_quantity']} 件`}]);
                    return;
                }
            }
        });
};

var refreshdeliveryOrderDetails = function() {
    if (!$("#building").customVal()) {
        return;
    }

    doAjaxPromise(`/api/v1/admin/Delivery/${deliveryId}/getOrderDetails?pageSize=99999999&building=${$("#building").customVal()}&all=${$('input[id="all"]:checked').val() ?? ''}`, "get", {})
        .then(function(xhr) {
            $('#deliveryOrderDetail').empty();
            var data = xhr.response.data, last = {'building': '', 'room': '', 'phone': '', 'status': 2, 'writeoffTime': null}, cacheStr = '', cacheRow = [], cacheRemark = [];
            for (var i = 0; i < data.length + 1; i++) {
                if (i > 0 && (i === data.length || (last['building'] !== data[i]['building'] || last['room'] !== data[i]['room']))) {
                    if (cacheRow.length) {
                        cacheStr += `<ul>${cacheRow.join('')}</ul>`;
                        if (cacheRemark.length > 1) {
                            cacheStr += `<p>备注：</p><ol><li>${cacheRemark.join('</li><li>')}</li></ol>`;
                        } else if (cacheRemark.length === 1) {
                            cacheStr += `<p>备注：${cacheRemark[0]}</p>`;
                        }
                        last['writeoffTime'] && (cacheStr += `<p>核销时间：${last['writeoffTime']}</p>`);
                        cacheRow = [];
                        cacheRemark = [];
                    }
                    $('#deliveryOrderDetail').append(renderDom(cacheStr, {'building': last['building'], 'room': last['room'], 'status': last['status']}));
                }
                if (i < data.length) {
                    if (last['building'] !== data[i]['building'] || last['room'] !== data[i]['room']) {
                        last['building'] = data[i]['building'];
                        last['room'] = data[i]['room'];
                        last['status'] = 2;
                        cacheStr = '';
                    }
                    if (last['phone'] !== data[i]['phone']) {
                        if (cacheRow.length) {
                            cacheStr += `<ul>${cacheRow.join('')}</ul>`;
                            cacheRow = [];
                        }
                        last['phone'] = data[i]['phone'];
                        last['status'] === 2 && (last['status'] = data[i]['status']);
                        cacheStr += `<h4>${data[i]['receiver']}</h4><p>点击拨打：<a href="tel:${data[i]['phone']}">${data[i]['phone']}</a></p>`;
                    }
                    var quantity = ~~data[i]['quantity'] > 1 ? `<span style="font-size: 28px; font-weight: bold;">${data[i]['quantity']}</span>` : data[i]['quantity'];
                    cacheRow.push(`<li>${data[i]['product']}&nbsp;&nbsp;<code>${quantity} 件</code></li>`);
                    if (data[i]['remark'] && cacheRemark.indexOf(data[i]['remark']) === -1) {
                        cacheRemark.push(data[i]['remark']);
                    }
                    last['writeoffTime'] = data[i]['writeoff_at'];
                }
            }
        })
        .catch(function(xhr) {
            console.log(arguments);
            toastr.error('订单列表拉取失败，请下拉刷新页面', '网络错误', { timeOut: 3000 });
        });
};

var renderDom = function(str, params) {
    var container = $('<div>').addClass('panel').addClass('panel-default'),
        panelHeading = $('<div>').addClass('panel-heading').append(`<h3 class="panel-title">${params['building']}-${params['room']}</h3>`),
        panelBody = $('<div>').addClass('panel-body').html(str),
        writeOff = $('<button>').addClass('btn')
            .addClass('btn-default')
            .attr('type', 'button')
            .text('核销')
            .click(function() {
                if ($('#claimBuilding').css('display') !== 'none') {
                    toastr.error('核销商品前需要先认领楼栋', { timeOut: 3000 });
                    return false;
                }
                $('#writeOffForm input[name="deliveryId"]').val(deliveryId);
                $('#writeOffForm input[name="building"]').val(params['building']);
                $('#writeOffForm input[name="room"]').val(params['room']);
                $('#writeOffModal div[class="modal-body"]').html(`<h3>${params['building']}-${params['room']}</h3><hr />${str}`);
                $('#writeOffModal').modal('show');
            });

    return container.append(panelHeading)
        .append(
            panelBody.append(
                $('<div>').addClass('text-right')
                    .append(params['status'] === 1 ? writeOff : writeOff.text('已核销')
                        .attr('disabled', 'disabled')
                    )
            )
        );
};

$(function() {
    $('#collapseSummaryTable').on('show.bs.collapse', function () {
        $('#headingSummaryTableHelp').html('隐藏');
    });

    $('#collapseSummaryTable').on('hide.bs.collapse', function () {
        $('#headingSummaryTableHelp').html('显示');
    });

    $("#calcSummaryTable").select2({
        ajax: {
            url: `/api/v1/admin/Delivery/${deliveryId}/getBuilding`,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    pageIndex: params.page,
                    pageSize: 100
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;

                return {
                    results: data.data.map(function(v) {
                        return {
                            'id': v['building'],
                            'name': v['building']
                        }
                    }),
                    pagination: {
                        more: (params.page * 100) < data.itemsCount
                    }
                };
            }
        },
        placeholder: ' 多选楼栋',
        escapeMarkup: function (markup) { return markup; },
        closeOnSelect: false,
        tags: false,
        minimumResultsForSearch: -1,
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

    $("#building").select2({
        ajax: {
            url: `/api/v1/admin/Delivery/${deliveryId}/getBuilding`,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    pageIndex: params.page,
                    pageSize: 100
                };
            },
            processResults: function (data, params) {
                params.page = params.page || 1;

                return {
                    results: data.data.map(function(v) {
                        var deliveryUser = v['delivery_user'] ?? '无';
                        return {
                            'id': v['building'],
                            'name': `${v['building']} - 配送员：${deliveryUser}${v['is_pickup'] ? '（自提）' : ''}，待发货 ${~~v['not_shipped_quantity'] ? '<span style="color: red; font-weight: bold;">' + v['not_shipped_quantity'] + '</span>' : v['not_shipped_quantity']} 件`,
                            'delivery_user': v['delivery_user']
                        }
                    }),
                    pagination: {
                        more: (params.page * 100) < data.itemsCount
                    }
                };
            }
        },
        placeholder: '选择楼栋进行配送',
        escapeMarkup: function (markup) { return markup; },
        minimumResultsForSearch: Infinity,
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

    $('#building').on('select2:opening select2:closing', function(event) {
        var $searchfield = $(this).parent().find('.select2-search__field');
        $searchfield.prop('disabled', true);
        $searchfield.attr('inputmode', 'none')
    });

    $("#claimBuildingForm select[name='pickup']").select2({
        minimumResultsForSearch: Infinity
    });

    $("#claimBuildingForm input[name='isPickup']").change(function() {
        if (~~this.value) {
            $("#claimBuildingForm select[name='pickup']").removeAttr('disabled');
        } else {
            $("#claimBuildingForm select[name='pickup']").attr('disabled', 'disabled');
        }
    });

    $("#building").on('select2:select', function(e) {
        var data = e.params.data;
        if (!data['delivery_user']) {
            var building = ~~data['id'] == data['id'] ? ` ${data['id']} 号楼` : data['id'];
            $('#claimBuilding').text(`认领${building}`).show();
            $('#claimBuildingText').html(building);
            $('#claimBuildingForm input[name="deliveryId"]').val(deliveryId);
            $('#claimBuildingForm input[name="building"]').val(data['id']);
        } else {
            $('#claimBuilding').hide();
        }

        refreshdeliveryOrderDetails();
        refreshBuildingSelect2();
    });

    setTimeout(function() {
        $('#all').on('change', refreshdeliveryOrderDetails);
    }, 0);

    submitCalcSummaryTable();
});