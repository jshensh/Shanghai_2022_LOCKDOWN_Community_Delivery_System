$(function() {
    typeof window.gridFilter !== 'undefined' && delete window.gridFilter;
    
    var groupBuyField = function (config) {
        jsGrid.Field.call(this, config);
    };

    groupBuyField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            return row["group_buy_title"] || '无';
        }
    });

    jsGrid.fields.groupBuy = groupBuyField;

    var sendStatusField = function (config) {
        jsGrid.Field.call(this, config);
    };

    sendStatusField.prototype = new jsGrid.Field({
        itemTemplate: function (value, row) {
            var code = {
                "FailedOperation.ContainSensitiveWord": "短信内容中含有敏感词",
                "FailedOperation.FailResolvePacket": "请求包解析失败",
                "FailedOperation.InsufficientBalanceInSmsPackage": "套餐包余量不足",
                "FailedOperation.JsonParseFail": "解析请求包体时候失败",
                "FailedOperation.MarketingSendTimeConstraint": "营销短信发送时间限制",
                "FailedOperation.PhoneNumberInBlacklist": "手机号在免打扰名单库中",
                "FailedOperation.SignatureIncorrectOrUnapproved": "签名未审批或格式错误",
                "FailedOperation.TemplateIncorrectOrUnapproved": "模板未审批或内容不匹配",
                "FailedOperation.TemplateParamSetNotMatchApprovedTemplate": "请求内容与审核通过的模板内容不匹配",
                "FailedOperation.TemplateUnapprovedOrNotExist": "模板未审批或不存在",
                "InternalError.OtherError": "其他错误",
                "InternalError.RequestTimeException": "请求发起时间不正常",
                "InternalError.RestApiInterfaceNotExist": "不存在该 RESTAPI 接口",
                "InternalError.SendAndRecvFail": "接口超时或短信收发包超时",
                "InternalError.SigFieldMissing": "后端包体中请求包体没有 Sig 字段或 Sig 为空",
                "InternalError.SigVerificationFail": "后端校验 Sig 失败",
                "InternalError.Timeout": "请求下发短信超时",
                "InternalError.UnknownError": "未知错误类型",
                "InvalidParameterValue.ContentLengthLimit": "请求的短信内容太长",
                "InvalidParameterValue.IncorrectPhoneNumber": "手机号格式错误",
                "InvalidParameterValue.ProhibitedUseUrlInTemplateParameter": "禁止在模板变量中使用 URL",
                "InvalidParameterValue.SdkAppIdNotExist": "SdkAppId 不存在",
                "InvalidParameterValue.TemplateParameterFormatError": "验证码模板参数格式错误",
                "InvalidParameterValue.TemplateParameterLengthLimit": "单个模板变量字符数超过12个",
                "LimitExceeded.AppCountryOrRegionDailyLimit": "业务短信国家/地区日下发条数超过设定的上限",
                "LimitExceeded.AppCountryOrRegionInBlacklist": "业务短信国家/地区不在国际港澳台短信发送限制设置的列表中而禁发",
                "LimitExceeded.AppDailyLimit": "业务短信日下发条数超过设定的上限",
                "LimitExceeded.AppGlobalDailyLimit": "业务短信国际/港澳台日下发条数超过设定的上限",
                "LimitExceeded.AppMainlandChinaDailyLimit": "业务短信中国大陆日下发条数超过设定的上限",
                "LimitExceeded.DailyLimit": "短信日下发条数超过设定的上限 (国际/港澳台)",
                "LimitExceeded.DeliveryFrequencyLimit": "下发短信命中了频率限制策略",
                "LimitExceeded.PhoneNumberCountLimit": "调用接口单次提交的手机号个数超过200个",
                "LimitExceeded.PhoneNumberDailyLimit": "单个手机号日下发短信条数超过设定的上限",
                "LimitExceeded.PhoneNumberOneHourLimit": "单个手机号1小时内下发短信条数超过设定的上限",
                "LimitExceeded.PhoneNumberSameContentDailyLimit": "单个手机号下发相同内容超过设定的上限",
                "LimitExceeded.PhoneNumberThirtySecondLimit": "单个手机号30秒内下发短信条数超过设定的上限",
                "MissingParameter.EmptyPhoneNumberSet": "传入的号码列表为空",
                "UnauthorizedOperation.IndividualUserMarketingSmsPermissionDeny": "个人用户没有发营销短信的权限",
                "UnauthorizedOperation.RequestIpNotInWhitelist": "请求 IP 不在白名单中",
                "UnauthorizedOperation.RequestPermissionDeny": "请求没有权限",
                "UnauthorizedOperation.SdkAppIdIsDisabled": "此 SdkAppId 禁止提供服务",
                "UnauthorizedOperation.SerivceSuspendDueToArrears": "欠费被停止服务",
                "UnauthorizedOperation.SmsSdkAppIdVerifyFail": "SmsSdkAppId 校验失败",
                "UnsupportedOperation.": "不支持该请求",
                "UnsupportedOperation.ChineseMainlandTemplateToGlobalPhone": "国内短信模板不支持发送国际/港澳台手机号",
                "UnsupportedOperation.ContainDomesticAndInternationalPhoneNumber": "群发请求里既有国内手机号也有国际手机号",
                "UnsupportedOperation.GlobalTemplateToChineseMainlandPhone": "国际/港澳台短信模板不支持发送国内手机号",
                "UnsupportedOperation.UnsuportedRegion": "不支持该地区短信下发"
            };
            return row["send_status"] ? '成功' : (`失败：${code[row['failed_reason']] || '未知错误'}`);
        }
    });

    jsGrid.fields.sendStatus = sendStatusField;

    $("#smsLogDetail").jsGrid({
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
            title: "ID",
            type: "text",
            align: "center",
            width: 40
        }, {
            name: "group_buy_title",
            title: "团购",
            type: "groupBuy",
            sorting: false,
            align: "center"
        }, {
            name: "phone",
            title: "手机号",
            type: "text",
            sorting: false,
            align: "center"
        }, {
            name: "content",
            title: "发送内容",
            type: "text",
            sorting: false,
            align: "center",
            width: 300
        }, {
            name: "send_user",
            title: "发送用户",
            type: "text",
            sorting: false,
            align: "center"
        }, {
            name: "send_status",
            title: "发送状态",
            type: "sendStatus",
            sorting: false,
            align: "center"
        }, {
            name: "length",
            title: "计费",
            type: "text",
            sorting: false,
            align: "center",
            width: 30
        }, {
            name: "created_at",
            title: "发送时间",
            type: "text",
            validate: "required",
            sorting: false,
            align: "center"
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

        controller: {
            loadData: function (filter) {
                if (typeof window.gridFilter !== "undefined") {
                    filter["filter"] = window.gridFilter;
                }
                return $.ajax({
                    type: "GET",
                    url: "/api/v1/admin/Sms/log",
                    data: filter
                }).done(function (data) {
                    $("#srvCount").text(data["itemsCount"]);
                });
            }
        }
    });

    $("#pageSize").change(function () {
        $("#smsLogDetail").jsGrid("option", "pageSize", ~~$(this).val());
        $.cookie("pageSize", ~~$(this).val());
    });

    $("#pageSize").val([$.cookie("pageSize") || 20]);
});

$("#group_buy_id").select2({
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
    placeholder: ' 搜索团购活动',
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

$('#send_status').select2({
    tags: false,
    minimumResultsForSearch: -1
});