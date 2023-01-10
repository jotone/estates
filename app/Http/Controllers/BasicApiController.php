<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Http\{JsonResponse, Request};

class BasicApiController extends Controller
{
    /**
     * Default query ordering
     * @var array
     */
    protected $order = ['id'];

    /**
     * Default order direction
     * @var string
     */
    protected $order_dir;

    /**
     * Default query page value
     * @var int
     */
    protected $page = 1;

    /**
     * Default query select field list
     * @var string
     */
    protected $select = '*';

    /**
     * Default query skip value
     * @var int
     */
    protected $skip = 0;

    /**
     * Default query number of limit items
     * @var int
     */
    protected $take = 25;

    /**
     * Apply different "where" queries on collection
     *
     * @param Builder $collection
     * @param array $where
     * @param string $func
     * @return Builder
     */
    protected function applyWhereQuery(Builder $collection, array $where, string $func): Builder
    {
        foreach ($where as $key => $value) {
            if (empty($value)) {
                $collection = $collection->{$func . 'Null'}($key);
            } else {
                $collection = str_contains($value, ',')
                    ? $collection->{$func . 'In'}($key, explode(',', $value))
                    : $collection->{$func}($key, $value);
            }
        }

        return $collection;
    }

    /**
     * Apply "with" query
     *
     * @param Builder $collection
     * @param string|array $with
     * @return Builder
     */
    protected function applyWithQuery(Builder $collection, string|array $with): Builder
    {
        // Check if "with" parameter is string and convert it to array
        if (!is_array($with)) {
            $with = [$with];
        }
        // Request query values
        $values = [];
        // Countable results
        $withQuery = [];
        // Relationships result
        $withCount = [];
        // Fill "values" array
        foreach ($with as $val) {
            // Convert value to array if the string contains coma
            if (str_contains($val, ',')) {
                $values = array_merge($values, explode(',', $val));
            } else {
                $values[] = $val;
            }
        }
        // Treat "values" array
        foreach ($values as $val) {
            // Dot signifies that string contains sub-query
            if (str_contains($val, '.')) {
                // Separate string value as target field and relation property
                [$field, $prop] = explode('.', $val);
                // The 'count' property signifies that the request wants just a relation amount number as result
                if ($prop == 'count') {
                    $withCount[] = $field;
                } else {
                    // Other values are treating as relations on the query model
                    $withQuery[$field] = function ($q) use ($prop) {
                        return $q->with(explode(',', $prop));
                    };
                }
            } else {
                $withQuery[] = $val;
            }
        }
        // Apply relation "count" sub-query
        if (!empty($withCount)) {
            $collection = $collection->withCount($withCount);
        }
        // Apply "with" query
        if (!empty($withQuery)) {
            $collection = $collection->with($withQuery);
        }

        return $collection;
    }

    /**
     * @param $collection
     * @param array $args
     * @return JsonResponse
     */
    protected function apiIndexResponse($collection, array $args): JsonResponse
    {
        // Apply "where" query
        if (!empty($args['where'])) {
            $collection = $this->applyWhereQuery($collection, $args['where'], 'where');
        }
        // Apply "whereNot" query
        if (!empty($args['where_not'])) {
            $collection = $this->applyWhereQuery($collection, $args['where_not'], 'whereNot');
        }
        // Apply "orWhere" query
        if (!empty($args['or_where'])) {
            $collection = $this->applyWhereQuery($collection, $args['or_where'], 'orWhere');
        }

        // Get total elements count
        $total = $collection->count();

        // Apply additional query relationships
        if (!empty($args['with'])) {
            $collection = $collection->with($args['with']);
        }
        // Apply order query
        foreach ($this->order as $field) {
            $collection = $collection->orderBy($field, $this->order_dir);
        }

        if ($this->take > 0) {
            $collection = $collection->take($this->take)->skip($this->skip);
        }

        return response()->json([
            // Get paginated collection
            'collection' => $collection->get()->map(function ($model) {
                if (method_exists(static::class, 'map')) {
                    $model = $this->map($model);
                }
                return $model;
            }),
            'page' => $this->page,
            'take' => $this->take,
            'total' => $total
        ]);
    }


    /**
     * Default api destroy method
     *
     * @param Model $model
     * @return JsonResponse
     * @throws \Exception
     */
    protected function destroyRequest(Model $model): JsonResponse
    {
        $model->delete();

        return response()->json([], 204);
    }

    /**
     * Default api index method response
     *
     * @param Request $request
     * @param string $model
     * @param $callback
     * @return JsonResponse
     */
    protected function listRequest(Request $request, string $model, $callback = null): JsonResponse
    {
        // Get request data
        $args = $this->parseData($request);

        // Run query
        $collection = method_exists(static::class, 'query') ? $this->query($model) : $model::query();

        // Set search value
        $search = $args['search'] ?? null;

        // Check search value isset
        if (!empty($search)) {
            $collection = empty($callback)
                ? $collection->where('name', 'like', '%' . $search . '%')
                : $callback($collection, $search);
        }

        return $this->apiIndexResponse($collection, $args);
    }

    /**
     * Parse request data
     *
     * @param Request $request
     * @return array
     */
    protected function parseData(Request $request): array
    {
        // Get request data
        $args = $request->only(['take', 'page', 'order', 'select', 'where', 'or_where', 'where_not', 'search', 'with']);

        // Set query selectable fields
        $this->select = $args['select'] ?? $this->select;
        // Set order fields
        if (!empty($args['order']['by'])) {
            $this->order = str_contains($args['order']['by'], ',')
                ? explode(',', $args['order']['by'])
                : [$args['order']['by']];
        }

        // Set order direction
        $this->order_dir = !empty($args['order']['dir']) && $args['order']['dir'] == 'desc' ? 'desc' : 'asc';
        // Set number of taken elements
        $this->take = $args['take'] ?? $this->take;
        // Set page number
        $this->page = !empty($args['page']) && is_numeric($args['page']) ? $args['page'] : 1;
        // Set Offset value
        $this->skip = $this->page > 1 && $this->take > 0 ? ($this->page - 1) * $this->take : 0;

        return $args;
    }

    /**
     * Default api show method response
     *
     * @param int $id
     * @param $model
     * @param Request $request
     * @return JsonResponse
     */
    protected function showRequest(int $id, $model, Request $request): JsonResponse
    {
        // Select specified fields
        if ($request->has('select')) {
            $model = $model->select(explode(',', $request->get('select')));
        }
        // Select relation
        if ($request->has('with')) {
            $model = $model->with($request->get('with'));
        }
        return response()->json($model->findOrFail($id));
    }
}