<?php

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class CustomerController
{
    public $email;
    public $customer;
    public $customerRepository;

    function __construct()
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

    function checkLogin()
    {
        if (empty($_SESSION['email'])) {
            $_SESSION['error'] = 'Vui lòng đăng nhập để thực hiện chức năng này';
            //Trở về trang chủ
            header('location:/');
            exit;
        }
    }
    //Hiển thị thông tin tài khoản
    function show()
    {
        $this->checkLogin();
        $customer = $this->customer;
        require ABSPATH_SITE . 'view/customer/show.php';
    }

    function updateInfo()
    {
        $this->checkLogin();
        $customer = $this->customer;
        $customer->setName($_POST['fullname']);
        $customer->setMobile($_POST['mobile']);

        $current_password = $_POST['current_password'];
        $new_password = $_POST['password'];
        if ($current_password && $new_password) {
            //kiểm tra password hiện tại nhập vào có giống với database không
            if (!password_verify($current_password, $customer->getPassword())) {
                $_SESSION['error'] = 'Sai mật khẩu hiện tại';
                header('location:/index.php?c=customer&a=show');
                exit;
            }

            //cập nhật mật khẩu mới vào object customer
            $encode_new_password = password_hash($new_password, PASSWORD_BCRYPT);
            $customer->setPassword($encode_new_password);
        }

        if ($this->customerRepository->update($customer)) {
            $_SESSION['name'] = $_POST['fullname'];
            $_SESSION['success'] = 'Đã cập nhật thông tin thành công';
            // trở về trang thông tin tài khoản
            header('location:/index.php?c=customer&a=show');
            exit;
        }
        $_SESSION['error'] = $this->customerRepository->getError();
        // trở về trang thông tin tài khoản
        header('location:/index.php?c=customer&a=show');
    }

    //Hiển thị thông tin giao hàng mặc định
    function defaultShipping()
    {
        $this->checkLogin();
        $customer = $this->customer;
        require ABSPATH_SITE . 'view/customer/defaultShipping.php';
    }

    // Hiển thị danh sách đơn hàng của người đăng nhập
    function orders()
    {
        $this->checkLogin();
        //Lấy danh sách đơn hàng của người đăng nhập
        $customer_id = $this->customer->getId();
        $orderRepository = new OrderRepository();
        $orders = $orderRepository->getByCustomerId($customer_id);
        require ABSPATH_SITE . 'view/customer/orders.php';
    }

    // Hiển thị chi tiết đơn hàng của người đăng nhập
    function orderDetail()
    {
        $this->checkLogin();
        //Lấy đơn hàng tương ứng với mã đơn hàng
        $orderId = $_GET['id'];
        $orderRepository = new OrderRepository();
        $order = $orderRepository->find($orderId);
        //check đơn hàng chính chủ (người đăng nhập)
        if ($order->getCustomerId() != $this->customer->getId()) {
            $_SESSION['error'] = 'Bạn không có quyền truy cập vào đơn hàng của người khác';
            // trở về trang thông tin tài khoản
            header('location:/index.php?c=customer&a=orders');
            exit;
        }
        require ABSPATH_SITE . 'view/customer/orderDetail.php';
    }

    function notExistingEmail()
    {
        //Nếu email đã đăng ký thì echo 'false'
        // Ngược lại thì echo 'true'
        // echo 'false';
        $email = $_GET['email'];
        $customer = $this->customerRepository->findEmail($email);
        if ($customer) {
            echo 'false';
            return;
        }
        echo 'true';
    }

    function register()
    {
        $secret = GOOGLE_RECAPTCHA_SECRET;
        $recaptcha = new \ReCaptcha\ReCaptcha($secret);
        $remoteIp = "127.0.0.1";
        $gRecaptchaResponse = $_POST['g-recaptcha-response'];
        $resp = $recaptcha->setExpectedHostname(get_host_name())
            ->verify($gRecaptchaResponse, $remoteIp);
        if ($resp->isSuccess()) {
            // Verified!
            $data = [];
            $data["name"] = $_POST['fullname'];
            $data["password"] = password_hash($_POST['password'], PASSWORD_BCRYPT);
            $data["mobile"] = $_POST['mobile'];
            $data["email"] = $_POST['email'];
            $data["login_by"] = 'form';
            $data["shipping_name"] = null;
            $data["shipping_mobile"] = null;
            $data["ward_id"] = null;
            $data["is_active"] = 0;
            $data["housenumber_street"] = null;

            if (!$this->customerRepository->save($data)) {
                $_SESSION['error'] = $this->customerRepository->getError();
                // về trang chủ
                header('location:/');
                exit;
            }
            //Gởi mail để verify account
            $emailServer = new EmailService();
            $to = $_POST['email'];
            $name = $_POST['fullname'];
            $subject = "Godashop: Verify Account";
            $domain = get_domain();
            //Cần mã hóa email để người dùng không sửa email được khi active

            $key = JWT_KEY;
            $payload = [
                'email' => $to
            ];
            $token = JWT::encode($payload, $key, 'HS256');

            $url = get_domain() .  "/index.php?c=customer&a=activeAccount&token=$token";
            $link_active_account = "<a href='$url'>Kích hoạt tài khoản</a>";
            $content = "
            Chào $name<br>
            Vui lòng click vào link bên dưới để kích hoạt tài khoản <br>
            $link_active_account <br>
            Email được gởi từ: $domain
            ";
            $emailServer->send($to, $subject, $content);

            //thành công
            $_SESSION['success'] = 'Đã tạo tài khoản thành công. Vui lòng kiểm tra email để xác nhận tài khoản';
            // về trang chủ
            header('location:/');
        } else {
            $errors = $resp->getErrorCodes();
            var_dump($errors);
        }
    }

    function activeAccount()
    {
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
        $customer = $this->customerRepository->findEmail($email);
        if ($customer) {
            $customer->setIsActive(1);
            $this->customerRepository->update($customer);
            $_SESSION['success'] = "Đã active tài khoản $email thành công";
            header('location:/');
            exit;
        }
        $_SESSION['error'] = "$email không tồn tại";
        header('location:/');
    }

    function forgotPassword()
    {
        //Gởi mail để verify account
        $emailServer = new EmailService();
        $to = $_POST['email'];
        //Kiểm tra email phải tồn tại trong hệ thống
        $customer = $this->customerRepository->findEmail($to);
        if (!$customer) {
            //thành công
            $_SESSION['error'] = "Email $to không tồn tại trong hệ thống. Vui lòng chọn email khác";
            // về trang chủ
            header('location:/');
            exit;
        }

        $subject = "Godashop: Reset Password";
        $domain = get_domain();
        //Cần mã hóa email để người dùng không sửa email được khi active

        $key = JWT_KEY;
        $payload = [
            'email' => $to
        ];
        $token = JWT::encode($payload, $key, 'HS256');
        $name = $customer->getName();
        $url = get_domain() .  "/index.php?c=customer&a=resetPassword&token=$token";
        $link_reset_password = "<a href='$url'>Reset Password</a>";
        $content = "
        Chào $name<br>
        Vui lòng click vào link bên dưới để reset password <br>
        $link_reset_password <br>
        Email được gởi từ: $domain
        ";
        $emailServer->send($to, $subject, $content);

        //thành công
        $_SESSION['success'] = "Đã gởi email để reset password. Vui lòng kiểm tra email $to";
        // về trang chủ
        header('location:/');
    }

    function resetPassword()
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

    function updatePassword()
    {
        // Giải mã token để lấy email
        $key = JWT_KEY;
        $jwt = $_POST['token'];
        // Giải mã
        try {
            $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        } catch (Exception $e) {
            $_SESSION['error'] = "You try hack!!!";
            header('location:/');
            exit;
        }
        $email = $decoded->email;
        // kiểm tra email có tồn tại trong hệ thống không
        $customer = $this->customerRepository->findEmail($email);
        if (!$customer) {
            $_SESSION['error'] = "$email không tồn tại trong hệ thống.";
            header('location:/');
            exit;
        }
        //update password
        $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
        $customer->setPassword($password);
        if (!$this->customerRepository->update($customer)) {
            $_SESSION['error'] = $this->customerRepository->getError();
            header('location:/');
            exit;
        }
        $_SESSION['success'] = "Đã reset mật khẩu thành công cho email $email";
        header('location:/');
    }

    function test1()
    {
        //mã hóa
        $key = 'godashop_khakha';
        $payload = [
            'email' => 'abc@gmail.com'
        ];
        $jwt = JWT::encode($payload, $key, 'HS256');
        echo $jwt;
    }

    function test2()
    {
        //giải mã hóa
        $key = 'godashop_khakha';
        $jwt = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJlbWFpbCI6ImFiY0BnbWFpbC5jb20ifQ.4M6UEkzgoAtAOSUilHJ0na40EasZEVjy5TWM_6_Jo6Q';
        $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
        var_dump($decoded);
    }

    function saveShippingAddress()
    {
        $customer = $this->customer;
        $customer->setWardId($_POST['ward']);
        $customer->setShippingName($_POST['fullname']);
        $customer->setShippingMobile($_POST['mobile']);
        $customer->setHousenumberStreet($_POST['address']);

        if (!$this->customerRepository->update($customer)) {
            $_SESSION['error'] = $this->customerRepository->getError();
            header('location:/');
            exit;
        }
        $_SESSION['success'] = "Đã cập nhật địa chỉ giao hàng mặc định thành công";
        header('location:/index.php?c=customer&a=defaultShipping');
    }
}