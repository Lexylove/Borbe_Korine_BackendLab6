<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * ProductController - protected CRUD API for products.
 * Every action requires a valid Bearer access token (JWT).
 *
 *  GET    /api/products            list (supports ?search=&page=&per_page=)
 *  GET    /api/products/{id}       show
 *  POST   /api/products            create
 *  PUT    /api/products/{id}       update  (PATCH also works - partial update)
 *  DELETE /api/products/{id}       delete
 */
class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        // Load api first so CORS/security headers are sent even if something later fails
        $this->call->library('api');
        $this->call->database();
        $this->call->model('ProductModel');
    }

    /** Authenticate and make sure the user's role grants the scope. */
    private function authorize($scope)
    {
        $auth = $this->api->require_jwt();
        $scopes = $auth['scopes'] ?? [];
        if (!in_array($scope, $scopes, true)) {
            $this->api->respond_error('Forbidden: you do not have permission to do this.', 403);
        }
        return $auth;
    }

    public function index()
    {
        $this->api->require_method('GET');
        $this->authorize('read');

        $q        = $this->api->get_query_params();
        $search   = (string) ($q['search'] ?? '');
        $page     = max(1, (int) ($q['page'] ?? 1));
        $per_page = min(100, max(1, (int) ($q['per_page'] ?? 100)));

        $total = $this->ProductModel->count_products($search);
        $rows  = $this->ProductModel->list_products($search, $per_page, ($page - 1) * $per_page);

        $this->api->respond([
            'data' => array_map([$this, 'format'], $rows),
            'meta' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $per_page,
                'pages'    => (int) ceil($total / $per_page),
            ],
        ]);
    }

    public function show($id)
    {
        $this->api->require_method('GET');
        $this->authorize('read');

        $product = $this->ProductModel->find_product($id);
        if (!$product) {
            $this->api->respond_error('Product not found.', 404);
        }
        $this->api->respond(['data' => $this->format($product)]);
    }

    public function store()
    {
        $this->api->require_method('POST');
        $this->authorize('write');

        [$data, $errors] = $this->validate($this->api->body(), true);
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed.', 'status' => 422, 'errors' => $errors], 422);
        }

        $id = $this->ProductModel->create_product($data);
        $this->api->respond([
            'message' => 'Product created.',
            'data'    => $this->format($this->ProductModel->find_product($id)),
        ], 201);
    }

    public function update($id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'PATCH'], true)) {
            $this->api->respond_error('Method Not Allowed', 405);
        }
        $this->authorize('write');

        $existing = $this->ProductModel->find_product($id);
        if (!$existing) {
            $this->api->respond_error('Product not found.', 404);
        }

        // PUT = full update, PATCH = partial update
        $full = $_SERVER['REQUEST_METHOD'] === 'PUT';
        [$data, $errors] = $this->validate($this->api->body(), $full);
        if ($errors) {
            $this->api->respond(['error' => 'Validation failed.', 'status' => 422, 'errors' => $errors], 422);
        }
        if (!$data) {
            $this->api->respond_error('Nothing to update.', 422);
        }

        $this->ProductModel->update_product($id, $data);
        $this->api->respond([
            'message' => 'Product updated.',
            'data'    => $this->format($this->ProductModel->find_product($id)),
        ]);
    }

    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->authorize('delete');

        if (!$this->ProductModel->find_product($id)) {
            $this->api->respond_error('Product not found.', 404);
        }
        $this->ProductModel->delete_product($id);
        $this->api->respond(['message' => 'Product deleted.']);
    }

    // ------------------------------------------------------------------

    /**
     * Validate input. $require_all = true -> every field is required.
     * @return array [cleaned data, errors]
     */
    private function validate(array $in, $require_all)
    {
        $data = [];
        $err  = [];

        if (array_key_exists('product_name', $in) || $require_all) {
            $v = trim((string) ($in['product_name'] ?? ''));
            if ($v === '')                     $err['product_name'] = 'Product name is required.';
            elseif (mb_strlen($v) > 100)       $err['product_name'] = 'Product name must be at most 100 characters.';
            else                               $data['product_name'] = $v;
        }

        if (array_key_exists('description', $in) || $require_all) {
            $v = $in['description'] ?? '';
            $data['description'] = is_scalar($v) ? trim((string) $v) : '';
        }

        if (array_key_exists('price', $in) || $require_all) {
            $v = $in['price'] ?? null;
            if (!is_numeric($v) || (float) $v < 0 || (float) $v > 99999999.99) {
                $err['price'] = 'Price must be a number between 0 and 99,999,999.99.';
            } else {
                $data['price'] = number_format((float) $v, 2, '.', '');
            }
        }

        if (array_key_exists('quantity', $in) || $require_all) {
            $v = $in['quantity'] ?? null;
            if (!is_numeric($v) || (string) (int) $v !== (string) (0 + $v) || (int) $v < 0 || (int) $v > 2147483647) {
                $err['quantity'] = 'Quantity must be a whole number (0 or more).';
            } else {
                $data['quantity'] = (int) $v;
            }
        }

        return [$data, $err];
    }

    private function format(array $p)
    {
        return [
            'id'           => (int) $p['id'],
            'product_name' => $p['product_name'],
            'description'  => $p['description'],
            'price'        => (float) $p['price'],
            'quantity'     => (int) $p['quantity'],
            'created_at'   => $p['created_at'],
        ];
    }
}
