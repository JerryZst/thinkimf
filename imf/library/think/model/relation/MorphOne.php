<?php
/**
 *
 * ============================================================================
 * [UD DataMap BigData System] Copyright (c) 1995-2028 www.unnnnn.com
 * 版权所有 1995-2028 UD数据信息有限公司【中国】，并保留所有权利。
 * This is not  free soft ware, use is subject to license.txt
 * 网站地址: http://www.unnnnn.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: 陈建华 $
 * $Create Time: 2018/2/9 0009 $
 * email:unnnnn@foxmail.com
 * function:Auth.php
 */

namespace think\model\relation;

use think\db\Query;
use think\Exception;
use think\Loader;
use think\Model;
use think\model\Relation;

class MorphOne extends Relation
{
    // 多态字段
    protected $morphKey;
    protected $morphType;
    // 多态类型
    protected $type;

    /**
     * 构造函数
     * @access public
     * @param  Model  $parent    上级模型对象
     * @param  string $model     模型名
     * @param  string $morphKey  关联外键
     * @param  string $morphType 多态字段名
     * @param  string $type      多态类型
     */
    public function __construct(Model $parent, $model, $morphKey, $morphType, $type)
    {
        $this->parent    = $parent;
        $this->model     = $model;
        $this->type      = $type;
        $this->morphKey  = $morphKey;
        $this->morphType = $morphType;
        $this->query     = (new $model)->db();
    }

    /**
     * 延迟获取关联数据
     * @access public
     * @param  string   $subRelation 子关联名
     * @param  \Closure $closure     闭包查询条件
     * @return Model
     */
    public function getRelation($subRelation = '', $closure = null)
    {
        if ($closure) {
            $closure($this->query);
        }

        $this->baseQuery();

        $relationModel = $this->query->relation($subRelation)->find();

        if ($relationModel) {
            $relationModel->setParent(clone $this->parent);
        }

        return $relationModel;
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param  string  $operator 比较操作符
     * @param  integer $count    个数
     * @param  string  $id       关联表的统计字段
     * @param  string  $joinType JOIN类型
     * @return Query
     */
    public function has($operator = '>=', $count = 1, $id = '*', $joinType = 'INNER')
    {
        return $this->parent;
    }

    /**
     * 根据关联条件查询当前模型
     * @access public
     * @param  mixed     $where 查询条件（数组或者闭包）
     * @param  mixed     $fields 字段
     * @return Query
     */
    public function hasWhere($where = [], $fields = null)
    {
        throw new Exception('relation not support: hasWhere');
    }

    /**
     * 预载入关联查询
     * @access public
     * @param  array    $resultSet   数据集
     * @param  string   $relation    当前关联名
     * @param  string   $subRelation 子关联名
     * @param  \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResultSet(&$resultSet, $relation, $subRelation, $closure)
    {
        $morphType = $this->morphType;
        $morphKey  = $this->morphKey;
        $type      = $this->type;
        $range     = [];

        foreach ($resultSet as $result) {
            $pk = $result->getPk();
            // 获取关联外键列表
            if (isset($result->$pk)) {
                $range[] = $result->$pk;
            }
        }

        if (!empty($range)) {
            $data = $this->eagerlyMorphToOne([
                [$morphKey, 'in', $range],
                [$morphType, '=', $type],
            ], $relation, $subRelation, $closure);

            // 关联属性名
            $attr = Loader::parseName($relation);

            // 关联数据封装
            foreach ($resultSet as $result) {
                if (!isset($data[$result->$pk])) {
                    $relationModel = null;
                } else {
                    $relationModel = $data[$result->$pk];
                    $relationModel->setParent(clone $result);
                    $relationModel->isUpdate(true);
                }

                $result->setRelation($attr, $relationModel);
            }
        }
    }

    /**
     * 预载入关联查询
     * @access public
     * @param  Model    $result      数据对象
     * @param  string   $relation    当前关联名
     * @param  string   $subRelation 子关联名
     * @param  \Closure $closure     闭包
     * @return void
     */
    public function eagerlyResult(&$result, $relation, $subRelation, $closure)
    {
        $pk = $result->getPk();

        if (isset($result->$pk)) {
            $pk   = $result->$pk;
            $data = $this->eagerlyMorphToOne([
                [$this->morphKey, '=', $pk],
                [$this->morphType, '=', $this->type],
            ], $relation, $subRelation, $closure);

            if (isset($data[$pk])) {
                $relationModel = $data[$pk];
                $relationModel->setParent(clone $result);
                $relationModel->isUpdate(true);
            } else {
                $relationModel = null;
            }

            $result->setRelation(Loader::parseName($relation), $relationModel);
        }
    }

    /**
     * 多态一对一 关联模型预查询
     * @access protected
     * @param  array         $where       关联预查询条件
     * @param  string        $relation    关联名
     * @param  string        $subRelation 子关联
     * @param  bool|\Closure $closure     闭包
     * @return array
     */
    protected function eagerlyMorphToOne($where, $relation, $subRelation = '', $closure = false)
    {
        // 预载入关联查询 支持嵌套预载入
        if ($closure) {
            $closure($this->query);
        }

        $list     = $this->query->where($where)->with($subRelation)->find();
        $morphKey = $this->morphKey;

        // 组装模型数据
        $data = [];

        foreach ($list as $set) {
            $data[$set->$morphKey] = $set;
        }

        return $data;
    }

    /**
     * 保存（新增）当前关联数据对象
     * @access public
     * @param  mixed $data 数据 可以使用数组 关联模型对象 和 关联对象的主键
     * @return Model|false
     */
    public function save($data)
    {
        if ($data instanceof Model) {
            $data = $data->getData();
        }
        // 保存关联表数据
        $pk = $this->parent->getPk();

        $model = new $this->model;

        $data[$this->morphKey]  = $this->parent->$pk;
        $data[$this->morphType] = $this->type;
        return $model->save($data) ? $model : false;
    }

    /**
     * 执行基础查询（进执行一次）
     * @access protected
     * @return void
     */
    protected function baseQuery()
    {
        if (empty($this->baseQuery) && $this->parent->getData()) {
            $pk = $this->parent->getPk();

            $this->query->where([
                [$this->morphKey, '=', $this->parent->$pk],
                [$this->morphType, '=', $this->type],
            ]);
            $this->baseQuery = true;
        }
    }

}
