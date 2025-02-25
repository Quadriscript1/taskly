<?php 
    include('constant.php'); // Include the database connection

    if(isset($_GET['id'])) {
        $id = $_GET['id'];

        // Delete user from database
        $sql = "DELETE FROM sign_up WHERE id=$id";
        $res = mysqli_query($conn, $sql);

        if($res == true) {
            $_SESSION['delete'] = "<div class='success'>User Deleted Successfully</div>";
        } else {
            $_SESSION['delete'] = "<div class='error'>Failed to Delete User</div>";
        }

        // Redirect back to the user list
        header('location: users-list.php');
    } else {
        // Redirect if no ID is provided
        header('location: users-list.php');
    }
?>
