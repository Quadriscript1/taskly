<?php 
    include('constant.php'); // Database connection

    if(isset($_GET['id'])) {
        $id = $_GET['id'];

        // Get user details from database
        $sql = "SELECT * FROM sign_up WHERE id=$id";
        $res = mysqli_query($conn, $sql);

        if($res == true) {
            $row = mysqli_fetch_assoc($res);
            $full_name = $row['full_name'];
            $email = $row['email'];
            $phone_number = $row['phone_number'];
        }
    }

    if(isset($_POST['update'])) {
        $id = $_POST['id'];
        $email = $_POST['email'];
        $phone_number = $_POST['phone_number'];

        // Update query
        $sql = "UPDATE sign_up SET 
                email='$email',
                phone_number='$phone_number' 
                WHERE id=$id";

        $res = mysqli_query($conn, $sql);

        if($res == true) {
            $_SESSION['update'] = "<div class='success'>User Updated Successfully</div>";
            header('location: users-list.php');
        } else {
            $_SESSION['update'] = "<div class='error'>Failed to Update User</div>";
            header('location: update-user.php?id='.$id);
        }
    }
?>

<html>
<head>
    <title>Update User</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

    <div class="container">
        <h1 class="text-center">Update User</h1> <br>

        <form action="" method="POST">
            <input type="hidden" name="id" value="<?php echo $id; ?>">

            <label>Full Name:</label>
            <input type="text" name="full_name" value="<?php echo $full_name; ?>" disabled><br><br>

            <label>Email:</label>
            <input type="email" name="email" value="<?php echo $email; ?>"><br><br>

            <label>Phone Number:</label>
            <input type="text" name="phone_number" value="<?php echo $phone_number; ?>"><br><br>

            <input type="submit" name="update" value="Update" class="btn-primary">
        </form>
    </div>

</body>
</html>
