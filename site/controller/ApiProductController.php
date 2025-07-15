<?php

class ApiProductController
{
    // Danh sách sản phẩm
    public function index()
    {
        $categoryRepository = new CategoryRepository();
        $categories = $categoryRepository->getAll();
        $conds = []; //mặc định
        $sorts = []; //mặc định
        $page = $_GET['page'] ?? 1; //mặc định là 1

        $list = $_GET['list'] ?? 4;
        $item_per_page = $_GET['item_per_page'] ?? 10;
        // Tìm sản phẩm theo danh mục
        $category_id = $_GET['category_id'] ?? null;
        if ($category_id) {
            $conds['category_id'] = [
                'type' => '=',
                'val' => $category_id,
            ];
            // SELECT * FROM view_product WHERE category_id=3
            $category = $categoryRepository->find($category_id);
            $categoryName = $category->getName();
        }

        $priceRange = $_GET['price-range'] ?? null;
        if ($priceRange) {
            $temp = explode('-', $priceRange);
            $startPrice = $temp[0];
            $endPrice = $temp[1];
            $conds['sale_price'] = [
                'type' => 'BETWEEN',
                'val' => "$startPrice AND $endPrice",
            ];

            // SELECT * FROM view_product WHERE sale_price BETWEEN 300000 AND 500000
            //Lớn hơn 1 triệu
            if ($endPrice == 'greater') {
                $conds['sale_price'] = [
                    'type' => '>=',
                    'val' => $startPrice,
                ];
            }
        }

        $search = $_GET['search'] ?? null;
        if ($search) {
            $conds['name'] = [
                'type' => 'LIKE',
                'val' => "'%$search%'",
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

        //featured
        $featured = $_GET['featured'] ?? null;
        if ($featured) {
            $sorts = ['featured' => 'DESC'];
        }

        //featured
        $latest = $_GET['latest'] ?? null;
        if ($latest) {
            $sorts = ['created_date' => 'DESC'];
        }

        $productRepository = new ProductRepository();
        $products = $productRepository->getBy($conds, $sorts, $page, $item_per_page);

        // //hierarchy
        $hierarchy = $_GET['hierarchy'] ?? null;
        if ($hierarchy) {
            // Biến để lưu trữ cấu trúc danh mục và sản phẩm tương ứng
            $categoryProducts = [];
            // Lấy sản phẩm theo từng danh mục
            foreach ($categories as $category) {
                $conds = [
                    'category_id' => [
                        'type' => '=',
                        'val' => $category->getId(),
                    ],
                ];
                $products = $productRepository->getBy($conds, $sorts, $page, $item_per_page);
                //SELECT * FROM view_product WHERE category_id=3

                // Phân trang
                $totalProducts = $productRepository->getBy($conds, $sorts);
                $totalPage = ceil(count($totalProducts) / $item_per_page);
                $totalItem = count($totalProducts);

                $categoryProducts[] = [
                    'items' => self::convertToAPIProducts($products),
                    'categoryName' => $category->getName(),
                    'totalItem' => $totalItem,
                    'pagination' => [
                        'page' => $page,
                        'totalPage' => $totalPage,

                    ],
                ];
            }
            $response = $categoryProducts;
            $response = json_encode($response);
            echo $response;
            return;
        }

        // Phân trang
        $totalProducts = $productRepository->getBy($conds, $sorts);
        $totalPage = ceil(count($totalProducts) / $item_per_page);
        $totalItem = count($totalProducts);

        $response = [
            'items' => self::convertToAPIProducts($products),
            'totalItem' => $totalItem,
            'pagination' => [
                'page' => $page,
                'totalPage' => $totalPage,
            ],
        ];
        $response = json_encode($response);
        echo $response;
    }

    public static function convertToAPIProduct($product)
    {
        $temp = get_object_vars($product);
        //unset($temp['description']);
        $temp['featured_image'] = get_domain() . '/upload/' . $temp['featured_image'];
        return $temp;
    }

    public static function convertToAPIProducts($products)
    {
        $temps = [];
        foreach ($products as $product) {
            $temps[] = self::convertToAPIProduct($product);
        }
        return $temps;
    }

    public function detail($id)
    {
        $productRepository = new ProductRepository();
        $product = $productRepository->find($id);
        $response = self::convertToAPIProduct($product);

        // Sản phẩm có liên quan
        $sorts = [];
        $conds = [
            'category_id' => [
                'type' => '=',
                'val' => $product->getCategoryId(),
            ],
            'id' => [
                'type' => '!=',
                'val' => $id,
            ],
        ];
        $relatedProducts = $productRepository->getBy($conds, $sorts, 1, 10);
        $relatedProducts = self::convertToAPIProducts($relatedProducts);
        $response['relatedProducts'] = $relatedProducts;

        // thumbnail
        $response['thumbnailItems'] = self::convertToAPIImageItems($product->getImageItems());

        $response = json_encode($response);
        echo $response;
    }

    public function convertToAPIImageItems($imageItems)
    {
        $temps = [];
        foreach ($imageItems as $imageItem) {
            $temp = get_object_vars($imageItem);
            //unset($temp['description']);
            $temp['name'] = get_domain() . '/upload/' . $temp['name'];
            $temps[] = $temp;
        }
        return $temps;
    }
    public function storeComment($product_id)
    {
        $info = json_decode(file_get_contents("php://input"));

        $data["email"] = $info->email;
        $data["fullname"] = $info->fullname;
        $data["star"] = $info->rating;
        $data["created_date"] = date('Y-m-d H:i:s');
        $data["description"] = $info->description;
        $data["product_id"] = $product_id;
        $commentRepository = new CommentRepository();
        //lưu xuống database
        $commentRepository->save($data);

        $productRepository = new ProductRepository();
        $product = $productRepository->find($product_id);
        $comments = $product->getComments();
        echo json_encode(self::convertToAPIComments($comments));
    }

    public function comments($product_id)
    {
        $productRepository = new ProductRepository();
        $product = $productRepository->find($product_id);
        $comments = $product->getComments();
        echo json_encode(self::convertToAPIComments($comments));
    }

    public function convertToAPIComments($comments)
    {
        $temps = [];
        foreach ($comments as $comment) {
            $temp = get_object_vars($comment);
            $temps[] = $temp;
        }
        return $temps;
    }

    public function findBrand($id)
    {
        $brandRepository = new BrandRepository();
        $brand = $brandRepository->find($id);
        $response = [
            'id' => $brand->getId(),
            'name' => $brand->getName(),
        ];
        echo json_encode($response);
    }

    public function ajaxSearch()
    {
        $search = $_GET['pattern'];
        $productRepository = new ProductRepository();
        $products = $productRepository->getByPattern($search);
        require ABSPATH_SITE . 'view/product/ajaxSearch.php';
    }
}
