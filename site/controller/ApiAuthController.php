<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ApiAuthController
{
    function login()
    {
        $info = json_decode(file_get_contents("php://input"));
        $email = $info->email;
        $password = $info->password;
        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->findEmail($email);
        // 1. If user không tồn tại thì báo lỗi email không tồn tại trong hệ thống
        if (empty($customer)) {

            header('HTTP/1.1 401 Unauthorized');
            echo 'Lỗi: không tồn tại email trong hệ thống';
            exit;
        }

        // 2. Nếu mật khẩu không đúng thì báo lỗi
        if (!password_verify($password, $customer->getPassword())) {
            header('HTTP/1.1 401 Unauthorized');
            echo 'Lỗi: Sai mật khẩu';
            exit;
        }

        // 3. Tài khoản đã bị inactive
        if ($customer->getIsActive() != 1) {
            header('HTTP/1.1 401 Unauthorized');
            echo 'Lỗi: Tài khoản chưa kích hoạt';
            exit;
        }

        $user = ApiCustomerController::convertToAPICustomer($customer);

        $payload = $user;
        $key = JWT_KEY;
        $access_token = JWT::encode($payload, $key, 'HS256');

        $data = [
            "user" => $user,
            "access_token" => $access_token
        ];

        $response = json_encode($data);

        echo $response;
    }

    function logout()
    {
        // Hủy session
        session_destroy();
        // Về lại trang chủ
        header('location: /');
    }
    function loginGoogle()
    {
        try {
            $info = json_decode(file_get_contents("php://input"));
            $token = $info->accessToken;
            /* API URL */
            $url = 'https://www.googleapis.com/oauth2/v3/userinfo';

            /* Init cURL resource */
            $ch = curl_init($url);

            /* Array Parameter Data */



            /* set the content type json */
            $headers = [];
            $headers[] = "Authorization: Bearer " . $token;
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            /* set return type json */
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            /* execute request */
            $result = curl_exec($ch);

            /* close cURL resource */
            curl_close($ch);
            $object = json_decode($result);
            $email = $object->email;
            $name = $object->name;

            self::createCustomerBySocial($email, $name, "google");

            $customerRepository = new CustomerRepository();
            $customer = $customerRepository->findEmail($email);

            $user = ApiCustomerController::convertToAPICustomer($customer);

            $payload = $user;
            $key = JWT_KEY;
            $access_token = JWT::encode($payload, $key, 'HS256');

            $data = [
                "user" => $user,
                "access_token" => $access_token
            ];

            $response = json_encode($data);

            echo $response;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    function createCustomerBySocial($email, $name, $type)
    {
        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->findEmail($email);

        if (empty($customer)) {
            //create new customer
            $data = array(
                "name" => $name,
                "mobile" => "",
                "password" => "",
                "email" => $email,
                "shipping_name" => $name,
                "shipping_mobile" => "",
                "ward_id" => null,
                "housenumber_street" => null,
                "login_by" => $type,
                "is_active" => 1
            );
            $customerRepository->save($data);
        }
    }

    function setupLoginEnv($email, $name, $remember_me = null)
    {
        $_SESSION["email"] = $email;
        $_SESSION["name"] = $name;
    }

    // https://godashop.com/index.php?c=auth&a=loginFacebook
    function loginFacebook()
    {

        try {
            $info = json_decode(file_get_contents("php://input"));
            $token = $info->accessToken;
            /* API URL */

            $url = "https://graph.facebook.com/v2.3/me?access_token=$token";

            /* Init cURL resource */
            $ch = curl_init($url);

            /* Array Parameter Data */



            /* set the content type json */
            $headers = [];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            /* set return type json */
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            /* execute request */
            $result = curl_exec($ch);

            /* close cURL resource */
            curl_close($ch);
            $object = json_decode($result);
            $email = $object->email ??  $object->id . '@gmail.com';
            $name = $object->name;

            self::createCustomerBySocial($email, $name, "facebook");

            $customerRepository = new CustomerRepository();
            $customer = $customerRepository->findEmail($email);

            $user = ApiCustomerController::convertToAPICustomer($customer);

            $payload = $user;
            $key = JWT_KEY;
            $access_token = JWT::encode($payload, $key, 'HS256');

            $data = [
                "user" => $user,
                "access_token" => $access_token
            ];

            $response = json_encode($data);

            echo $response;
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
