<?php

namespace app\common\service;

/**
 * 订单导出服务
 *
 * @author  jshensh <admin@imjs.work>
 */

use think\facade\Request;
use think\Db;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use \PhpOffice\PhpSpreadsheet\Cell\DataType;

class ExportOrder
{

    private $model = null,
            $columns = [],
            $finished = false,
            $fieldsAllowedToSearch = [],
            $searchCallbackFunctions = [],
            $inputFields = [],
            $mergeCell,
            $callbacks = [];

    /**
     * 构造方法
     *
     * @access private
     *
     * @throws \Exception
     *
     * @param \think\db\Query|\think\model $model 需要操作的数据表模型
     */
    private function __construct($model, $mergeCell)
    {
        $this->model = $model;
        $this->mergeCell = $mergeCell;
        $this->columns = Db::getConnection()->getFieldsType($model->getTable());
        if (!$this->columns) {
            throw new \Exception('Table has no columns');
        }
        array_walk($this->columns, function(&$v) {
            $v = strstr($v, '(', true);
        });
        $this->inputFields = array_merge([
            'filter'  => [],
            'columns' => []
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

        foreach ($this->inputFields['columns'] as $key => $value) {
            if (!is_numeric($value)) {
                unset($this->inputFields['columns'][$key]);
            }
        }

        $this->inputFields['columns'] = array_merge([
            'phone'       => 6,
            'address'     => 4,
            'receiver'    => 5,
            'serial'      => 1,
            'remark'      => 7,
            'product'     => 2,
            'quantity'    => 3,
            'writeoff_at' => 8,
        ], $this->inputFields['columns']);

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
        return $this->model
            ->field(['id', 'group_buy_id', 'group_concat(serial) as serial', 'product', 'quantity', 'building', 'room', 'receiver', 'phone', 'remark', 'sum(quantity) as quantity', 'writeoff_at'])
            ->where('status', '<>', 0)
            ->orderRaw('building + 0')
            ->orderRaw('room + 0')
            ->order('phone')
            ->orderRaw('serial + 0')
            ->group('building, room, phone, product')
            ->select();
    }

    public function setProperties($callback)
    {
        if ($callback instanceof \Closure) {
            $this->callbacks['setProperties'] = $callback;
        }
        return $this;
    }

    /**
     * 获取结果
     *
     * @access public
     *
     * @return string
     */
    public function export()
    {
        $this->finish();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('宋体')->setSize(11);
        $spreadsheet->getDefaultStyle()->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        if (isset($this->callbacks['setProperties'])) {
            $this->callbacks['setProperties']($spreadsheet->getProperties());
        }
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);
        $worksheet->getDefaultRowDimension()->setRowHeight(18);

        $mergeFromRow = [
            'phone'       => 0,
            'address'     => 0,
            'receiver'    => 0,
            'serial'      => 0,
            'remark'      => 0,
            'product'     => 0,
            'quantity'    => 0,
            'writeoff_at' => 0,
        ];

        foreach ($this->inputFields['columns'] as $key => $col) {
            $worksheet->setCellValueByColumnAndRow($col, 1, ucfirst($key));
        }

        $orders = $this->select();

        foreach ($orders as $i => $order) {
            foreach ($mergeFromRow as $key => $fromRow) {
                $col = $this->inputFields['columns'][$key];
                switch ($key) {
                    case 'address':
                        $worksheet->setCellValueExplicitByColumnAndRow($col, $i + 2, "{$order->building}-{$order->room}", DataType::TYPE_STRING);
                        break;
                    case 'quantity':
                        $worksheet->setCellValueExplicitByColumnAndRow($col, $i + 2, $order->{$key}, DataType::TYPE_NUMERIC);
                        $worksheet->getStyleByColumnAndRow($col, $i + 2)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                        if ($order->{$key} > 1) {
                            $worksheet->getStyleByColumnAndRow($col, $i + 2)->applyFromArray([
                                'font' => [
                                    'size' => 16,
                                    'bold' => true,
                                    'underline' => true
                                ]
                            ]);
                        }
                        break;
                    default:
                        $worksheet->setCellValueExplicitByColumnAndRow($col, $i + 2, $order->{$key} ?? '', DataType::TYPE_STRING);
                }
                if ($this->mergeCell) {
                    if (
                        $i > 0 &&
                        in_array($key, ['serial', 'address', 'receiver', 'phone', 'remark', 'writeoff_at'], true) &&
                        (
                            $key === 'address' ?
                            ($orders[$i - 1]->building === $orders[$i]->building && $orders[$i - 1]->room === $orders[$i]->room) :
                            (trim($orders[$i]->{$key}) && $orders[$i - 1]->{$key} === $orders[$i]->{$key})
                            
                        )
                    ) {
                        $tmpColumnIndexString = Coordinate::stringFromColumnIndex($col);
                        if ($i - $mergeFromRow[$key] > 0) {
                            $worksheet->unmergeCells($tmpColumnIndexString . ($mergeFromRow[$key] + 1) . ':' . $tmpColumnIndexString . ($i + 1));
                        }
                        $worksheet->mergeCells($tmpColumnIndexString . ($mergeFromRow[$key] + 1) . ':' . $tmpColumnIndexString . ($i + 2));
                    } else {
                        $mergeFromRow[$key] = $i + 1;
                    }
                }
            }
        }

        foreach ($this->inputFields['columns'] as $col) {
            $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }

        $highest = $worksheet->getHighestRowAndColumn();
        $worksheet->getStyle("A1:{$highest['column']}{$highest['row']}")->applyFromArray([
            'borders' => [
                'inside' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD8D8D8'],
                ],
            ],
        ]);

        ob_start();
        $writer = IOFactory::createWriter($spreadsheet, "Xlsx");
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        return $xlsData;
    }

    /**
     * __toString
     *
     * @access public
     *
     * @return string
     */
    public function __toString()
    {
        return $this->export();
    }

    /**
     * 创建实例
     *
     * @static
     *
     * @access public
     *
     * @param string|\think\db\Query|\think\model|\think\model\relation\HasMany $model Model 类名或实例化后的对象
     * @param bool $mergeCell 是否合并单元格
     *
     * @throws \Exception
     *
     * @return \app\common\service\Paginator
     */
    public static function create($model, $mergeCell = true)
    {
        if (is_string($model)) {
            if (!class_exists($model)) {
                throw new \Exception("Class {$model} not found");
            }
            $model = new $model;
        }

        if (is_object($model) && ($model instanceof \think\db\Query || $model instanceof \think\model || $model instanceof \think\model\relation\HasMany)) {
            return new self($model, $mergeCell);
        }

        throw new \Exception('Model not supported');
    }
}