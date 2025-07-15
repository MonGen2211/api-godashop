<?php
class ApiContactController
{
    // Hiển thị form
    public function form()
    {
        require ABSPATH_SITE . 'view/contact/form.php';
    }

    // Gởi email đến shop owner
    public function sendEmail()
    {
        $info = json_decode(file_get_contents("php://input"));
        $name = $info->fullname;
        $email = $info->email;
        $mobile = $info->mobile;
        $message = $info->content;
        $website = get_domain();
        $content = "
        Xin chào chủ cửa hàng,<br>
        Dưới đây là thông tin của khách hàng liên hệ: <br>
        Tên: $name, <br>
        Email: $email, <br>
        SDT: $mobile, <br>
        Nội dung: $message<br>
        Được gởi từ: $website
        ";

        $to = SHOP_OWNER;
        $subject = 'Godashop - Liên hệ';
        $emailService = new EmailService();
        $reply = $email;
        if ($emailService->send($to, $subject, $content, $reply)) {
            echo 'Đã gởi mail thành công';
        } else {
            echo '<span style="color:red">' . $emailService->message . '</span>';
        }
    }
}
