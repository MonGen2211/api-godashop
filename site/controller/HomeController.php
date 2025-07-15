<?php
class HomeController
{
    function index()
    {
        $productRepository = new ProductRepository();
        $conds = [];
        $sorts = ['featured' => 'DESC'];
        $page = 1;
        $item_per_page = 4;
        $featuredProducts = $productRepository->getBy($conds, $sorts, $page, $item_per_page);

        $sorts = ['created_date' => 'DESC'];
        $latestProducts = $productRepository->getBy($conds, $sorts, $page, $item_per_page);

        // Lấy tất cả các danh mục 
        $categoryRepository = new CategoryRepository();
        $categories = $categoryRepository->getAll();
        // Biến để lưu trữ cấu trúc danh mục và sản phẩm tương ứng
        $categoryProducts = [];
        // Lấy sản phẩm theo từng danh mục
        foreach ($categories as $category) {
            $conds = [
                'category_id' => [
                    'type' => '=',
                    'val' => $category->getId()
                ]
            ];
            $products = $productRepository->getBy($conds, $sorts, $page, $item_per_page);
            //SELECT * FROM view_product WHERE category_id=3
            $categoryProducts[] = [
                'categoryName' => $category->getName(),
                'products' => $products
            ];
        }

        require ABSPATH_SITE . 'view/home/index.php';
    }
}