<?php 
    include('constant.php'); // Database connection

    // Fetch all users
    $sql = "SELECT * FROM sign_up";
    $res = mysqli_query($conn, $sql);
?>

<html>
<head>
    <title>Users List</title>
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>

    <div class="container">
        <h1 class="text-center">Registered Users</h1> <br>

        <table border="1" cellspacing="0" cellpadding="10" class="table">
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Email</th>
                <th>Phone Number</th>
                <th>Actions</th>
            </tr>

            <?php 
                if($res == TRUE) {
                    $count = mysqli_num_rows($res); 

                    if($count > 0) {
                        while($row = mysqli_fetch_assoc($res)) {
                            $id = $row['id'];
                            $full_name = $row['full_name'];
                            $email = $row['email'];
                            $phone_number = $row['phone_number'];
                            ?>

                        <tr>
                            <td><?php echo $id; ?></td>
                            <td><?php echo $full_name; ?></td>
                            <td><?php echo $email; ?></td>
                            <td><?php echo $phone_number; ?></td>
                            <td>
                                <a href="update-user.php?id=<?php echo $id; ?>" class="btn-secondary">Edit</a>
                                <a href="delete-user.php?id=<?php echo $id; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
                            </td>
                        </tr>


                            <?php
                        }
                    } else {
                        echo "<tr><td colspan='5' class='error'>No users found</td></tr>";
                    }
                }
            ?>

        </table>
    </div>

</body>
</html>
