<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * ProductModel - all database access for the `products` table.
 */
class ProductModel extends Model
{
    protected $table = 'products';
    protected $primary_key = 'id';

    public function list_products($search = '', $limit = 100, $offset = 0)
    {
        $this->db->table($this->table);
        if ($search !== '') {
            $this->db->grouped(function ($q) use ($search) {
                $q->like('product_name', '%' . $search . '%')
                  ->or_like('description', '%' . $search . '%');
            });
        }
        return $this->db->order_by('id', 'DESC')->limit($limit, $offset)->get_all() ?: [];
    }

    public function count_products($search = '')
    {
        $this->db->table($this->table);
        if ($search !== '') {
            $this->db->grouped(function ($q) use ($search) {
                $q->like('product_name', '%' . $search . '%')
                  ->or_like('description', '%' . $search . '%');
            });
        }
        return (int) $this->db->count();
    }

    public function find_product($id)
    {
        return $this->db->table($this->table)->where('id', (int) $id)->get() ?: null;
    }

    public function create_product(array $data)
    {
        $this->db->table($this->table)->insert([
            'product_name' => $data['product_name'],
            'description'  => $data['description'],
            'price'        => $data['price'],
            'quantity'     => $data['quantity'],
        ]);
        return (int) $this->db->last_id();
    }

    public function update_product($id, array $data)
    {
        return $this->db->table($this->table)->where('id', (int) $id)->update($data);
    }

    public function delete_product($id)
    {
        return $this->db->table($this->table)->where('id', (int) $id)->delete();
    }
}
