<?php
class ProductController
{
    // Danh sách sản phẩm
    function index($category_id = null, $priceRange = null)
    {
        $categoryRepository = new CategoryRepository();
        $categories = $categoryRepository->getAll();
        $conds = []; //mặc định
        $sorts = []; //mặc định
        $page = $_GET['page'] ?? 1; //mặc định là 1
        $item_per_page = 10;
        // Tìm sản phẩm theo danh mục
        // $category_id = $_GET['category_id'] ?? null;
        if ($category_id) {
            $conds = [
                'category_id' => [
                    'type' => '=',
                    'val' => $category_id
                ]
            ];
            // SELECT * FROM view_product WHERE category_id=3
            $category = $categoryRepository->find($category_id);
            $categoryName = $category->getName();
        }

        // $priceRange = $_GET['price-range'] ?? null;
        if ($priceRange) {
            $temp = explode('-', $priceRange);
            $startPrice = $temp[0];
            $endPrice = $temp[1];
            $conds = [
                'sale_price' => [
                    'type' => 'BETWEEN',
                    'val' => "$startPrice AND $endPrice"
                ]
            ];

            // SELECT * FROM view_product WHERE sale_price BETWEEN 300000 AND 500000
            //Lớn hơn 1 triệu
            if ($endPrice == 'greater') {
                $conds = [
                    'sale_price' => [
                        'type' => '>=',
                        'val' => $startPrice
                    ]
                ];
            }
        }

        $search = $_GET['search'] ?? null;
        if ($search) {
            $conds = [
                'name' => [
                    'type' => 'LIKE',
                    'val' => "'%$search%'"
                ]
            ];
            //SELECT * FROM view_product WHERE name LIKE '%kem%'
        }

        // sort
        $sort = $_GET['sort'] ?? null;
        if ($sort) {
            $temp = explode('-', $sort);
            $order = $temp[1]; //chứa asc hoặc desc
            $col = $temp[0];
            $map = ['price' => 'sale_price', 'alpha' => 'name', 'created' => 'created_date'];
            $colName = $map[$col];
            $sorts = [$colName => $order];
        }
        $productRepository = new ProductRepository();
        $products = $productRepository->getBy($conds, $sorts, $page, $item_per_page);

        // Phân trang
        $totalProducts = $productRepository->getBy($conds, $sorts);
        $totalPage = ceil(count($totalProducts) / $item_per_page);
        require ABSPATH_SITE . 'view/product/index.php';
    }

    function detail($slugName, $id)
    {
        // Hỗ trợ sidebar
        $categoryRepository = new CategoryRepository();
        $categories = $categoryRepository->getAll();
        $productRepository = new ProductRepository();
        $product = $productRepository->find($id);
        //Hỗ trợ sidebar
        $category_id = $product->getCategoryId();
        $category = $categoryRepository->find($category_id);
        $categoryName = $category->getName();

        // Sản phẩm có liên quan
        $sorts = [];
        $conds = [
            'category_id' => [
                'type' => '=',
                'val' => $category_id
            ],
            'id' => [
                'type' => '!=',
                'val' => $id
            ]
        ];
        $relatedProducts = $productRepository->getBy($conds, $sorts);

        require ABSPATH_SITE . 'view/product/detail.php';
    }

    function storeComment()
    {
        $data["email"] = $_POST['email'];
        $data["fullname"] = $_POST['fullname'];
        $data["star"] = $_POST['rating'];
        $data["created_date"] = date('Y-m-d H:i:s');
        $data["description"] = $_POST['description'];
        $data["product_id"] = $_POST['product_id'];
        $commentRepository = new CommentRepository();
        //lưu xuống database
        $commentRepository->save($data);

        $productRepository = new ProductRepository();
        $product = $productRepository->find($_POST['product_id']);
        require ABSPATH_SITE . 'view/product/commentList.php';
    }

    function ajaxSearch()
    {
        $search = $_GET['pattern'];
        $productRepository = new ProductRepository();
        $products = $productRepository->getByPattern($search);
        require ABSPATH_SITE . 'view/product/ajaxSearch.php';
    }
}