;(function ($, window, document, undefined) {

    /*global jQuery, console*/

    'use strict';

    var pluginName = 'treeView'

    var permissionEleTemplate = '<li class="list-group-item node-permissionTreeView" data-nodeid={{__nodeId__}} data-parentnodeid={{__parentNodeId__}}{{__isShow__}}>\
    <div class="level">{{__level__}}<span class="icon glyphicon{{__expandIcon__}}"></span><span class="icon node-icon"></span></div>\
    <div class="permissionRow">\
        <div class="labelDiv">\
            <label class="control-label">{{__txt__}}</label><span class="hidden-xs">&nbsp;&nbsp;&nbsp;</span>\
        </div>\
        <div class="valDiv">\
            {{__val__}}\
        </div>\
    </div>\
</li>\
', 
        permissionValEleTemplate = '        <label class="checkbox-inline">\
            <input name="permission[{{__id__}}][]" type="checkbox" value="{{__permissionVal__}}"> {{__permissionTxt__}}\
        </label>';

    var Tree = function (element, data) {
        this.$element = $(element);
        this.nodesLength = 0;
        this.subscribeEvents();
        this.init(data);
    };

    Tree.prototype.init = function (data) {
        this.$element.html($('<ul />').append(this.buildTree(data, 0)).addClass('list-group')).addClass('treeview');
    };

    Tree.prototype.unsubscribeEvents = function () {
        this.$element.off('click');
    };

    Tree.prototype.subscribeEvents = function () {
        this.unsubscribeEvents();
        this.$element.on('click', $.proxy(this.clickHandler, this));
    };

    Tree.prototype.toggleExpandedState = function (nodeId, isChild) {
        if (!nodeId) {
            return;
        }

        var self = this, parentStatus;

        var $parentDom = this.$element.find('li.list-group-item[data-nodeid="' + nodeId + '"]'),
            $parentDomIcon = $parentDom.children('.level').children('.glyphicon');

        if ($parentDomIcon.hasClass('glyphicon-minus')) {
            $parentDomIcon.removeClass('glyphicon-minus').addClass('glyphicon-plus');
            parentStatus = 0;
        } else if ($parentDomIcon.hasClass('glyphicon-plus')) {
            if (!isChild) {
                $parentDomIcon.removeClass('glyphicon-plus').addClass('glyphicon-minus');
                parentStatus = 1;
            }
        }

        this.$element.find('li.list-group-item[data-parentnodeid="' + nodeId + '"]').each(function() {
            if (parentStatus === void 0) {
                return;
            }
            var $dom = $(this);
            if (parentStatus) {
                $dom.show();
            } else {
                $dom.hide();
                self.toggleExpandedState($dom.data('nodeid'), true);
            }
        });
    };

    Tree.prototype.clickHandler = function (event) {
        var target = $(event.target);
        if (target.is('input') || target.is('label')) {
            return;
        }

        var nodeId = target.data('nodeid') || target.parents('li.list-group-item:first').data('nodeid');
        
        this.toggleExpandedState(nodeId, false);
    };

    Tree.prototype.buildTree = function (data, level) {
        if (!data) {
            return;
        }

        if (Object.prototype.toString.apply(data) === '[object Array]') {
            var op = [];
            for (var i in data) {
                op = op.concat(this.buildTree(data[i], level));
            }
            return op;
        }

        var op = [];

        this.nodesLength++;

        op.push(
            permissionEleTemplate
                .replace(/\{\{__txt__\}\}/g, data['txt'])
                .replace(/\{\{__level__\}\}/g, new Array(level + 1).join('<span class="indent"></span>'))
                .replace(/\{\{__expandIcon__\}\}/g, (typeof data['children'] === 'object') ? ' expand-icon ' + (level > 0 ? 'glyphicon-plus' : 'glyphicon-minus') : '')
                .replace(/\{\{__nodeId__\}\}/g, data['id'])
                .replace(/\{\{__parentNodeId__\}\}/g, data['parent'])
                .replace(/\{\{__isShow__\}\}/g, level > 1 ? ' style="display: none;"' : '')
                .replace('{{__val__}}', (function() {
                    try {
                        var valObj = JSON.parse(data['val']);
                    } catch(e) {
                        console.error(e);
                        return '';
                    }
                    var valEleArr = [];
                    for (var j in valObj) {
                        valEleArr.push(permissionValEleTemplate
                            .replace(/\{\{__id__\}\}/g, data['id'])
                            .replace(/\{\{__permissionVal__\}\}/g, Math.pow(2, j))
                            .replace(/\{\{__permissionTxt__\}\}/g, valObj[j])
                            .replace(/\{\{__parent__\}\}/g, data['parent'])
                        );

                        if (typeof data['children'] === 'object') {
                            break;
                        }
                    }
                    return valEleArr.join("\n");
                })())
        );

        if (typeof data['children'] === 'object') {
            op = op.concat(this.buildTree(data['children'], level + 1));
        }

        return op;
    };

    $.fn[pluginName] = function (data) {
        var result;

        this.each(function () {
            var _this = $.data(this, pluginName);

            $.data(this, pluginName, new Tree(this, data));
        });

        return result || this;
    };

})(jQuery, window, document);


$(function() {
    $('#permissionTreeView').on('onselectstart,ondrag', function() {
        return false;
    });

    var getChildCheckboxNode = function(nodeId) {
        var nodes = [], childLiNode = typeof nodeId === 'number' ? $('li[data-parentnodeid="' + nodeId + '"]') : nodeId,
            inStack = arguments[1] || false;

        for (var i = 0; i < childLiNode.length; i++) {
            nodes.push(childLiNode[i]);
            var tmpChildLiNode = $('li[data-parentnodeid="' + $(childLiNode[i]).data('nodeid') + '"]');
            tmpChildLiNode.length && (nodes = nodes.concat(getChildCheckboxNode(tmpChildLiNode, true)));
        }

        return inStack ? nodes : $(nodes).find('input[type="checkbox"]');
    };

    var getParentCheckboxNode = function(nodeId) {
        var liNode = $('li[data-nodeid="' + nodeId + '"]');
        if (!liNode.length) {
            return false;
        }
        var checkboxNode = liNode.find('input[type="checkbox"]');
        if (!checkboxNode.length) {
            var parentLiNode = $('li[data-nodeid="' + nodeId + '"]'),
                parentLiNodeId = parentLiNode.data('parentnodeid');
            if (!parentLiNodeId) {
                return false;
            }
            var parentCheckboxNode = getParentCheckboxNode(parentLiNodeId);
            if (parentCheckboxNode === false) {
                return false;
            }

            return parentCheckboxNode;
        } else {
            return checkboxNode;
        }
    };

    var createPermissionTreeView = function(data) {
        $('#permissionTreeView').treeView(data);

        $('#permissionTreeView').on('click', 'input[type="checkbox"]', function(event) {
            var liNode = $(this).parents('li:first');

            /*
             * 权限树选择框逻辑：
             * 1. 父级的选中与子级无关，取消则取消子级所有已选中项
             * 2. 子级的取消与父级无关，选中则选中所有父级未选中项
             *
             * 即：二级功能的使用必需一级首页的访问权限，一级首页的访问与二级功能无关
             *
             * 选中父级时子级没有全部被选中以及取消全部子级时父级没有被取消，这些不是 bug！不是 bug！不是 bug！
             */

            if ($(this).prop('checked')) {
                var checkboxNode = getParentCheckboxNode(liNode.data('parentnodeid'), true);
                checkboxNode.length && checkboxNode.filter(':not(:checked)').prop('checked', true);
            } else {
                var checkboxNode = getChildCheckboxNode(liNode.data('nodeid'));
                checkboxNode.length && checkboxNode.filter(':checked').prop('checked', false);
            }
        });
    };

    doAjaxPromise('/api/v1/admin/Permission', 'get', {})
        .then(function(xhr) {
            data = xhr.response;
            createPermissionTreeView(data);
            var pathArr = location.pathname.split('/'),
                groupId = pathArr[pathArr.length - 1].match(/^\d+/);
            if (groupId) {
                getGroupInfo(groupId[0]);
            } else {
                $('button[type="submit"]').removeAttr('disabled');
            }
        }).catch(function(data) {
            toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "请求失败");
        });

    var getGroupInfo = function(groupId) {
        doAjaxPromise('/api/v1/admin/UserGroup/' + groupId, 'get', {})
            .then(function(xhr) {
                data = xhr.response;
                $('#editUserGroupForm input[name="name"]').val(data['name']);
                $('#editUserGroupForm input[name="id"]').val(groupId);

                for (var i in data['permission']) {
                    var endP = data['permission'][i]['perm'];
                    for (var p = 1; p <= endP; p *= 2) {
                        if (p & data['permission'][i]['perm']) {
                            $('input[name="permission[' + data['permission'][i]['scope_id'] + '][]"][value=' + p + ']').prop('checked', true);
                        }
                    }
                }
                $('button[type="submit"]').removeAttr('disabled');
            }).catch(function(data) {
                $('#mainContainer').html($('<div class="alert alert-danger" role="alert">请求项不存在，点击<a href="/dashboard/user_group/index.html" class="alert-link" data-pjax>这里</a>返回用户组列表</div>'));
                customPjax("#mainContainer a[data-pjax]", "#mainContainer");
            });
    };
});

var submitForm = function(formDom) {
    var fDom = $(formDom),
        nameDom = fDom.find('input[name="name"]'),
        submitButton = fDom.find('button[type="submit"]'),
        name = nameDom.val(),
        groupId = ~~(fDom.find('input[name="id"]').val()),
        action = '/api/v1/admin/UserGroup' + (groupId ? `/${groupId}` : '');

    if (!name) {
        toastr.error('用户组名不能为空', '数据不合法');
        return false;
    }

    submitButton.attr('disabled', 'disabled');

    $.ajax({
        type: groupId ? 'PUT' : 'POST',
        url: action,
        data: fDom.serialize()
    }).then(function(data, status) {
        toastr.success('更新成功');
        setTimeout(function() {
            history.go(-1);
        }, 500);
    }).fail(function(data) {
        submitButton.removeAttr('disabled');
        toastr.error(typeof data["responseJSON"] === 'undefined' || !data["responseJSON"] ? '未知错误' : (data["responseJSON"]["error"] || '未知错误'), "提交失败");
    });
};