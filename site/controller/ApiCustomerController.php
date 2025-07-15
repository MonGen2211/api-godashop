<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ApiCustomerController
{
    public $email;
    public $customer;
    public $customerRepository;

    public function __construct()
    {
        $customerRepository = new CustomerRepository();
        if (!empty($_SESSION['email'])) {
            $email = $_SESSION['email'];
            $customer = $customerRepository->findEmail($email);
            $this->email = $email;
            $this->customer = $customer;
        }
        $this->customerRepository = $customerRepository;
    }

    public function checkLogin()
    {
        if (empty($_SESSION['email'])) {
            $_SESSION['error'] = 'Vui lòng đăng nhập để thực hiện chức năng này';
            //Trở về trang chủ
            header('location:/');
            exit;
        }
    }
    //Hiển thị thông tin tài khoản
    public function show($id)
    {
        global $globalCustomer;
        if ($globalCustomer->id != $id) {
            echo "{}";
            return;
        }
        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->find($id);
        $response = json_encode(self::convertToAPICustomer($customer));
        echo $response;
    }

    public static function convertToAPICustomer($customer)
    {
        $temp = get_object_vars($customer);
        unset($temp['password']); //không gởi về password
        $selected_ward = $customer->getWard();
        $temp['ward_id'] = null;
        $temp['province_id'] = null;
        $temp['district_id'] = null;
        if (!empty($selected_ward)) {
            $temp['ward_id'] = $selected_ward->getId(); // 2 selected_ward_id
            $selected_district = $selected_ward->getDistrict();
            $temp['district_id'] = $selected_district->getId(); //3

            $selected_province = $selected_district->getProvince();
            $temp['province_id'] = $selected_province->getId(); //4
        }
        return $temp;
    }

    public function updateInfo($customerId)
    {
        global $globalCustomer;
        if ($globalCustomer->id != $customerId) {
            echo "{}";
            return;
        }

        $info = json_decode(file_get_contents("php://input"));
        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->find($customerId);
        $customer->setName($info->fullname);
        $customer->setMobile($info->mobile);

        $current_password = $info->current_password;
        $new_password = $info->password;
        if ($current_password && $new_password) {
            //kiểm tra password hiện tại nhập vào có giống với database không
            if (!password_verify($current_password, $customer->getPassword())) {
                header('HTTP/1.1 401 Unauthorized');
                echo "Sai mật khẩu hiện tại";
                exit;
            }

            //cập nhật mật khẩu mới vào object customer
            $encode_new_password = password_hash($new_password, PASSWORD_BCRYPT);
            $customer->setPassword($encode_new_password);
        }

        if (!$customerRepository->update($customer)) {
            header('HTTP/1.1 500 Internal Server Error');
            echo $customerRepository->getError();
            exit;
        }

        $customer = $customerRepository->find($customerId);
        $response = json_encode(self::convertToAPICustomer($customer));
        echo $response;
    }

    //Hiển thị thông tin giao hàng mặc định
    public function defaultShipping()
    {
        $this->checkLogin();
        $customer = $this->customer;
        require ABSPATH_SITE . 'view/customer/defaultShipping.php';
    }

    // Hiển thị danh sách đơn hàng của người đăng nhập
    public function orders()
    {
        // $this->checkLogin();
        //Lấy danh sách đơn hàng của người đăng nhập
        global $globalCustomer;
        $email = $globalCustomer->email;
        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->findEmail($email);
        $orderRepository = new OrderRepository();
        $orders = $orderRepository->getByCustomerId($customer->getId());
        $temps = [];
        foreach ($orders as $order) {
            $temps[] = self::convertToAPIOrder($order);
        }
        $response = json_encode($temps);
        echo $response;
    }

    public function convertToAPIOrder($order)
    {
        $temp = get_object_vars($order);
        $temp['order_items'] = [];
        $temp['status_description'] = $order->getStatus()->getDescription();
        $ward = $order->getShippingWard();
        $district = $ward->getDistrict();
        $province = $district->getProvince();
        $temp['ward_name'] = $ward->getName();
        $temp['district_name'] = $district->getName();
        $temp['province_name'] = $province->getName();
        $orderItems = $order->getOrderItems();
        foreach ($orderItems as $orderItem) {
            $t = get_object_vars($orderItem);
            $t['product'] = ApiProductController::convertToAPIProduct($orderItem->getProduct());
            $temp['order_items'][] = $t;
        }
        return $temp;
    }

    // Hiển thị chi tiết đơn hàng của người đăng nhập
    public function orderDetail($orderId)
    {
        // $this->checkLogin();
        //Lấy đơn hàng tương ứng với mã đơn hàng
        global $globalCustomer;

        $orderRepository = new OrderRepository();
        $order = $orderRepository->find($orderId);
        if ($globalCustomer->id != $order->customer_id) {
            echo "{}";
            return;
        }
        $order = self::convertToAPIOrder($order);
        $response = json_encode($order);
        echo $response;
    }

    public function notExistingEmail($email)
    {
        //Nếu email đã đăng ký thì echo 'false'
        // Ngược lại thì echo 'true'
        // echo 'false';

        $customer = $this->customerRepository->findEmail($email);
        if ($customer) {
            echo 'false';
            return;
        }
        echo 'true';
    }

    public function register()
    {
        $info = json_decode(file_get_contents("php://input"));

        $secret = GOOGLE_RECAPTCHA_SECRET;
        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        $remoteIp = "127.0.0.1";
        $gRecaptchaResponse = $info->recaptcha;
        //localhost sẽ thay bằng host cụ thể khi public website
        $resp = $recaptcha->setExpectedHostname(get_client_host_name())
            ->verify($gRecaptchaResponse, $remoteIp);
        if ($resp->isSuccess()) {
            // Verified!
            $data = [];
            $data["name"] = $info->fullname;
            $data["password"] = password_hash($info->password, PASSWORD_BCRYPT);
            $data["mobile"] = $info->mobile;
            $data["email"] = $info->email;
            $data["login_by"] = 'form';
            $data["shipping_name"] = null;
            $data["shipping_mobile"] = null;
            $data["ward_id"] = null;
            $data["is_active"] = 0;
            $data["housenumber_street"] = null;

            if (!$this->customerRepository->save($data)) {
                header('HTTP/1.1 500 Internal Server Error');
                echo $this->customerRepository->getError();
                exit;
            }
            //Gởi mail để verify account
            $emailServer = new EmailService();
            $to = $info->email;
            $name = $info->fullname;
            $subject = "Godashop: Verify Account";
            $domain = get_client_domain();
            //Cần mã hóa email để người dùng không sửa email được khi active

            $key = JWT_KEY;
            $payload = [
                'email' => $to,
            ];
            $token = JWT::encode($payload, $key, 'HS256');

            $url = $domain . "/active_account?token=$token";
            $link_active_account = "<a href='$url'>Kích hoạt tài khoản</a>";
            $content = "
            Chào $name<br>
            Vui lòng click vào link bên dưới để kích hoạt tài khoản <br>
            $link_active_account <br>
            Email được gởi từ: $domain
            ";
            $emailServer->send($to, $subject, $content);

            //thành công
            $customer = $this->customerRepository->findEmail($to);
            $response = json_encode(
                [
                    'message' => 'Đã tạo tài khoản thành công. Vui lòng vào email để kích hoạt tài khoản',
                    'user' => self::convertToAPICustomer($customer),

                ]
            );
            echo $response;
        } else {
            $errors = $resp->getErrorCodes();
            header('HTTP/1.1 500 Internal Server Error');
            echo implode('. ', $errors);
            exit;
        }
    }

    public function activeAccount()
    {
        $key = JWT_KEY;
        $jwt = $_GET['token'];
        // Giải mã
        try {
            $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'You try hack!!!';
            exit;
        }

        $email = $decoded->email;
        $customer = $this->customerRepository->findEmail($email);
        if ($customer) {
            $customer->setIsActive(1);
            $this->customerRepository->update($customer);
            $user = self::convertToAPICustomer($customer);

            $payload = $user;
            $key = JWT_KEY;
            $access_token = JWT::encode($payload, $key, 'HS256');

            $data = [
                "user" => $user,
                "access_token" => $access_token,
            ];

            $response = json_encode($data);

            echo $response;
            exit;
        }
        header('HTTP/1.1 500 Internal Server Error');
        echo "$email không tồn tại";
    }

    public function forgotPassword()
    {
        //Gởi mail để verify account
        $emailServer = new EmailService();
        $info = json_decode(file_get_contents("php://input"));
        $to = $info->email;
        //Kiểm tra email phải tồn tại trong hệ thống
        $customer = $this->customerRepository->findEmail($to);
        if (!$customer) {
            header('HTTP/1.1 500 Internal Server Error');
            echo "Email $to không tồn tại trong hệ thống. Vui lòng chọn email khác";
            exit;
        }

        $subject = "Godashop: Reset Password";
        $domain = get_client_domain();
        //Cần mã hóa email để người dùng không sửa email được khi active

        $key = JWT_KEY;
        $payload = [
            'email' => $to,
        ];
        $token = JWT::encode($payload, $key, 'HS256');
        $name = $customer->getName();
        $url = $domain . "/reset_password?token=$token";
        $link_reset_password = "<a href='$url'>Reset Password</a>";
        $content = "
        Chào $name<br>
        Vui lòng click vào link bên dưới để reset password <br>
        $link_reset_password <br>
        Email được gởi từ: $domain
        ";
        $emailServer->send($to, $subject, $content);

        //thành công
        echo "Đã gởi email để reset password. Vui lòng kiểm tra email $to";
        // về trang chủ

    }

    public function resetPassword()
    {
        // Giải mã token để lấy email
        $key = JWT_KEY;
        $jwt = $_GET['token'];
        // Giải mã
        try {
            $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        } catch (Exception $e) {
            $_SESSION['error'] = "You try hack!!!";
            header('location:/');
            exit;
        }
        $email = $decoded->email;
        $token = $jwt;
        require ABSPATH_SITE . 'view/customer/resetPassword.php';
    }

    public function updatePassword()
    {
        // Giải mã token để lấy email
        $key = JWT_KEY;
        $info = json_decode(file_get_contents("php://input"));
        $jwt = $_GET['token'];
        // Giải mã
        try {
            $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        } catch (Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo 'You try hack';
            exit;
        }
        $email = $decoded->email;
        // kiểm tra email có tồn tại trong hệ thống không
        $customer = $this->customerRepository->findEmail($email);
        if (!$customer) {
            header('HTTP/1.1 500 Internal Server Error');
            echo "$email không tồn tại trong hệ thống.";
            exit;
        }
        //update password
        $password = password_hash($info->password, PASSWORD_BCRYPT);
        $customer->setPassword($password);
        if (!$this->customerRepository->update($customer)) {
            header('HTTP/1.1 500 Internal Server Error');
            echo $this->customerRepository->getError();
            exit;
        }
        echo "Đã reset mật khẩu thành công cho email $email";
    }

    public function test1()
    {
        //mã hóa
        $key = 'godashop_khakha';
        $payload = [
            'email' => 'abc@gmail.com',
        ];
        $jwt = JWT::encode($payload, $key, 'HS256');
        echo $jwt;
    }

    public function test2()
    {
        //giải mã hóa
        $key = 'godashop_khakha';
        $jwt = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImFiY0BnbWFpbC5jb20ifQ.4M6UEkzgoAtAOSUilHJ0na40EasZEVjy5TWM_6_Jo6Q';
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        var_dump($decoded);
    }

    public function saveShippingAddress($customerId)
    {
        global $globalCustomer;
        if ($globalCustomer->id != $customerId) {
            echo "{}";
            return;
        }

        $info = json_decode(file_get_contents("php://input"));
        $customerRepository = new CustomerRepository();
        $customer = $customerRepository->find($customerId);
        $customer->setWardId($info->ward);
        $customer->setShippingName($info->fullname);
        $customer->setShippingMobile($info->mobile);
        $customer->setHousenumberStreet($info->address);

        if (!$this->customerRepository->update($customer)) {
            header('HTTP/1.1 500 Internal Server Error');
            echo $this->customerRepository->getError();
            exit;
        }
        $customer = $customerRepository->find($customerId);
        $response = json_encode(self::convertToAPICustomer($customer));
        echo $response;
    }
}