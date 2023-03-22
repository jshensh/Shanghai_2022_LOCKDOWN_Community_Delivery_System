<?php

namespace app\common\service;

/**
 * Model 搜索与分页服务
 *
 * @author  jshensh <admin@imjs.work>
 */

use think\facade\Request;
use think\Db;

class Paginator implements \JsonSerializable
{
    private $model = null,
            $columns = [],
            $finished = false,
            $fieldsAllowedToSearch = [],
            $perPage = 10,
            $searchCallbackFunctions = [],
            $inputFields = [];

    /**
     * 构造方法
     *
     * @access private
     *
     * @throws \Exception
     *
     * @param \think\db\Query|\think\model $model 需要操作的数据表模型
     * @param bool $checkColumns 检查数据表模型的字段
     */
    private function __construct($model, $checkColumns)
    {
        $this->model = $model;
        $this->columns = Db::getConnection()->getFieldsType($model->getTable());
        if ($checkColumns && !$this->columns) {
            throw new \Exception('Table has no columns');
        }
        $this->columns = $this->columns ? $this->columns : [];
        array_walk($this->columns, function(&$v) {
            $v = strstr($v, '(', true);
        });
        $this->inputFields = array_merge([
            'pageSize'  => 20,
            'pageIndex' => 1,
            'filter'    => [],
            'sortField' => in_array('id', array_keys($this->columns)) ? 'id' : key($this->columns),
            'sortOrder' => 'asc',
        ], Request::get());
    }

    /**
     * 魔术方法，调用 model 相应方法
     *
     * @access public
     *
     * @param string $func 方法名
     * @param array $argus 调用参数
     *
     * @return \app\common\service\Paginator
     */
    public function __call($func, $argus)
    {
        $this->model = call_user_func_array([$this->model, $func], $argus);

        if ($func === 'order') {
            $this->inputFields['sortField'] = null;
            $this->inputFields['sortOrder'] = null;
        }

        return $this;
    }

    /**
     * 限制允许搜索的字段
     *
     * @access public
     *
     * @param array $fields 需要限制的字段
     *
     * @return \app\common\service\Paginator
     */
    public function allowSearch($fields)
    {
        $this->fieldsAllowedToSearch = array_intersect(array_keys($this->columns), $fields);
        return $this;
    }

    /**
     * 使用自定义方法进行搜索
     *
     * @access public
     *
     * @param string $key 需要搜索的字段
     * @param callable $callback 回调函数
     *
     * @return \app\common\service\Paginator
     */
    public function search($key, $callback)
    {
        if (isset($this->columns[$key])) {
            $this->searchCallbackFunctions[$key] = $callback;
        }
        return $this;
    }

    private function finish()
    {
        if ($this->finished) {
            return $this;
        }

        if (!$this->fieldsAllowedToSearch) {
            $this->fieldsAllowedToSearch = array_keys($this->columns);
        }

        foreach ($this->inputFields['filter'] as $key => $value) {
            if (in_array($key, $this->fieldsAllowedToSearch)) {
                if (isset($this->searchCallbackFunctions[$key])) {
                    $this->model = $this->searchCallbackFunctions[$key]($this->model, $value, $this->inputFields);
                    continue;
                }

                switch ($this->columns[$key]) {
                    case 'int':
                    case 'integer':
                    case 'tinyint':
                    case 'smallint':
                    case 'mediumint':
                    case 'bigint':
                    case 'float':
                    case 'double':
                    case 'decimal':
                        $this->model = $this->model->where($key, '=', $value);
                        break;
                    default:
                        $this->model = $this->model->where($key, 'like', "%{$value}%");
                }
            }
        }

        if ($this->inputFields['sortField'] && $this->inputFields['sortOrder']) {
            $this->inputFields['sortField'] = $this->inputFields['sortField'] === 'id2' ? 'id' : $this->inputFields['sortField'];
            $this->model = $this->model->order($this->inputFields['sortField'], $this->inputFields['sortOrder']);
        }

        $this->finished = true;
    }

    /**
     * 获取 Model
     *
     * @access public
     *
     * @return array
     */
    public function select()
    {
        $this->finish();
        return $this->model->select();
    }

    /**
     * 获取结果
     *
     * @access public
     *
     * @return array
     */
    public function paginate()
    {
        $this->finish();

        if (
            isset($this->inputFields['pageSize']) &&
            filter_var($this->inputFields['pageSize'], FILTER_VALIDATE_INT) !== false &&
            (int) $this->inputFields['pageSize'] >= 1
        ) {
            $this->perPage = (int) $this->inputFields['pageSize'];
        }

        $result = $this->model->paginate($this->perPage, null, ['page' => $this->inputFields['pageIndex']])->toArray();

        // return [
        //     'current_page' => $result['current_page'],
        //     'last_page'    => $result['last_page'],
        //     'data'         => $result['data'],
        //     'page_size'    => $result['per_page'],
        //     'total'        => $result['total'],
        // ];

        return [
            'data'       => $result['data'],
            'itemsCount' => $result['total'],
        ];
    }

    /**
     * JsonSerializable
     *
     * @access public
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->paginate();
    }

    /**
     * 创建分页器
     *
     * @static
     *
     * @access public
     *
     * @param string|\think\db\Query|\think\model $model Model 类名或实例化后的对象
     * @param bool $checkColumns 检查数据表模型的字段
     *
     * @throws \Exception
     *
     * @return \app\common\service\Paginator
     */
    public static function create($model, $checkColumns = true)
    {
        if (is_string($model)) {
            if (!class_exists($model)) {
                throw new \Exception("Class {$model} not found");
            }
            $model = new $model;
        }

        if (is_object($model) && ($model instanceof \think\db\Query || $model instanceof \think\model)) {
            return new self($model, $checkColumns);
        }

        throw new \Exception('Model not supported');
    }
}