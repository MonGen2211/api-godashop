<?php
class ContactController
{
    // Hiển thị form
    function form()
    {
        require ABSPATH_SITE . 'view/contact/form.php';
    }

    // Gởi email đến shop owner
    function sendEmail()
    {
        $name = $_POST['fullname'];
        $email = $_POST['email'];
        $mobile = $_POST['mobile'];
        $message = $_POST['content'];
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
