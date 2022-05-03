<?php

namespace Gpxcat\LaravelQueryBuilder;

use Gpxcat\LaravelQueryBuilder\QueryBuilderException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;

class QueryBuilderServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
    }

    public function boot()
    {
        /**
         * 根據傳入的參數做客製化搜尋
         * @param mix $searchPairs 搜尋參數規則
         */
        Builder::macro('whereAll', function ($searchPairs) {
            $this->where(function (Builder $query) use ($searchPairs) {
                $searchPairs = Arr::wrap($searchPairs);
                foreach ($searchPairs as $key => $value) {
                    if (str_contains($key, '%')) {
                        $queries = explode('%', $key);
                        $columnName = $queries[0];
                        $clause = $queries[1] ?? '';
                        $isRelation = str_contains($columnName, '.');
                        if ($isRelation) {
                            $query->buildRelation($columnName, $clause, $value, $queries);
                        } else {
                            $query->whereClause($clause, $value, $queries);
                        }
                    } else {
                        $isRelation = str_contains($key, '.');
                        if ($isRelation) {
                            $query->buildRelation($key, '', $value, []);
                        } else {
                            $query->whereNormal($key, $value);
                        }
                    }
                }
            });

            return $this;
        });

        /**
         * 根據輸入字串做相對應的sql搜尋
         * @param string $clause Where Clause
         * @param string $value 搜尋值
         * @param array $queries 搜尋語法
         */
        Builder::macro('whereClause', function ($clause, $value, $queries) {
            switch ($clause) {
                case 'between':
                    $from = $queries[2] ?? '';
                    $to = $queries[3] ?? '';
                    $this->whereBetween($queries[0], [$from, $to]);
                    break;
                case 'date':
                    $from = $queries[2] ?? '';
                    $to = $queries[3] ?? '';
                    $this->where($queries[0], '>=', $from . ' 00:00:00')
                        ->where($queries[0], '<=', $to . ' 23:59:59');
                    break;
                case 'in':
                    if ($value) {
                        $this->whereIn($queries[0], $value);
                    }
                    break;
                case 'null':
                    $this->whereNull($queries[0]);
                    break;
                case 'notNull':
                    $this->whereNotNull($queries[0]);
                    break;
                case 'moreOrEqual':
                    $this->where($queries[0], '>=', $value);
                    break;
                case 'moreThan':
                    $this->where($queries[0], '>', $value);
                    break;
                case 'lessOrEqual':
                    $this->where($queries[0], '<=', $value);
                    break;
                case 'like':
                    $this->where($queries[0], 'LIKE', "%{$value}%");
                    break;
                default:
                    break;
            }
        });

        /**
         * 一般的where搜尋
         * @param string $relationAttribute 搜尋的欄位
         * @param string $value 搜尋值
         */
        Builder::macro('whereNormal', function ($relationAttribute, $value) {
            $relationAttribute = $this->getModel()->getTable() . '.' . $relationAttribute;
            if (is_array($value) && $value) { //多選的情況
                $this->whereIn($relationAttribute, $value);
            } else {
                $this->where($relationAttribute, $value);
            }
        });

        /**
         * 根據輸入字串組出關聯語法
         * @param string $columnName 前端傳遞字串 ex: getOrder:getEvent.name
         * @param string $clause Where Clause
         * @param string $value 搜尋值
         * @param string $queries 搜尋語法
         */
        Builder::macro('buildRelation', function ($columnName, $clause, $value, $queries) {
            $explode = explode('.', $columnName);
            $relationAttribute = $explode[1];
            $queries[0] = $relationAttribute;
            //因為以前的用法是用"."來帶出要查詢的欄位
            //避免要全部修改寫法 改成用":"來串接多個關聯 在這個地方再改回來"."去符合官方寫法
            $relations = str_replace(':', '.', $explode[0]);
            $this->whereHas($relations, function (Builder $query) use ($clause, $value, $queries, $relationAttribute) {
                if ($clause) {
                    $query->whereClause($clause, $value, $queries);
                } else {
                    $query->whereNormal($relationAttribute, $value);
                }
            });
        });

        \DB::query()->macro('firstOrFail', function () {
            if ($record = $this->first()) {
                return $record;
            }

            throw new QueryBuilderException('No records found.');
        });
    }
}
