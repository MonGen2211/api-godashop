<?php
class ApiCategoryController
{
    // Danh mục sản phẩm
    function index($category_id = null, $priceRange = null)
    {
        $categoryRepository = new CategoryRepository();
        $categories = $categoryRepository->getAll();
        $totalItem = count($categories);
        $response = [
            'items' =>  self::convertToAPICategory($categories),
            'totalItem' => $totalItem,
        ];
        $response = json_encode($response);
        echo $response;
    }

    function convertToAPICategory($categories)
    {
        $temps = [];
        foreach ($categories as $category) {
            $temp = get_object_vars($category);
            $temps[] = $temp;
        }
        return $temps;
    }
}
