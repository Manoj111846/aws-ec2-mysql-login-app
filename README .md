# AWS EC2 Public-Private PHP MySQL Login Application

## 1. Project Overview

This project demonstrates a simple web application hosted on AWS EC2 with a Public EC2 web server and a Private EC2 MySQL database server.

- Public EC2: Apache + PHP
- Private EC2: MySQL
- VPC: Public and Private subnets
- NAT Gateway: Internet access for the private subnet
- Security Groups: SSH, HTTP, and MySQL access control
- GitHub: Source-code repository

## 2. Architecture

```text
                         INTERNET
                            |
                    Public EC2
                  Apache + PHP
                Private IP: 123.11.1.25
                            |
                         TCP 3306
                            |
                            v
                   Private EC2
                       MySQL
                Private IP: 123.11.1.49
                            |
                            v
                        loginapp
                            |
                            v
                          users
```

> Private IP addresses can change after an EC2 stop/start. Always verify the current IPs in the AWS console.

---

# 3. AWS VPC Setup

## Step 1 - Create VPC

Create a VPC with:

```text
VPC CIDR: 123.11.1.0/24
```

## Step 2 - Create Subnets

Create:

- Public subnet for the Public EC2
- Private subnet for the Private EC2

Private subnet used in this lab:

```text
123.11.1.32/27
```

## Step 3 - Internet Gateway

Create an Internet Gateway and attach it to the VPC.

## Step 4 - NAT Gateway

Create a NAT Gateway in the Public subnet.

The Private subnet route table should contain:

```text
0.0.0.0/0 -> NAT Gateway
```

## Step 5 - Route Tables

### Public route table

```text
0.0.0.0/0 -> Internet Gateway
```

Associate it with the Public subnet.

### Private route table

```text
0.0.0.0/0 -> NAT Gateway
```

Associate it with the Private subnet.

---

# 4. Security Groups

## Public-EC2-SG

Inbound:

| Type | Port | Source |
|---|---:|---|
| SSH | 22 | My IP |
| HTTP | 80 | 0.0.0.0/0 |

## Private-EC2-SG

Inbound:

| Type | Port | Source |
|---|---:|---|
| SSH | 22 | Public-EC2-SG |
| MySQL/Aurora | 3306 | Public-EC2-SG |

---

# 5. Public EC2

Launch Ubuntu EC2 in the Public subnet.

Current private IP used in this lab:

```text
123.11.1.25
```

Install Apache:

```bash
sudo apt update
sudo apt install -y apache2
sudo systemctl start apache2
sudo systemctl enable apache2
sudo systemctl status apache2
```

Expected:

```text
Active: active (running)
```

---

# 6. Install PHP on Public EC2

```bash
sudo apt install -y php php-mysql
```

Check PHP:

```bash
php -v
```

Check MySQLi:

```bash
php -m | grep mysqli
```

Expected:

```text
mysqli
```

---

# 7. Private EC2 and MySQL

Launch Ubuntu EC2 in the Private subnet.

Current private IP used in this lab:

```text
123.11.1.49
```

Connect through AWS Systems Manager Session Manager.

Install MySQL:

```bash
sudo apt update
sudo apt install -y mysql-server
sudo systemctl start mysql
sudo systemctl enable mysql
sudo systemctl status mysql
```

---

# 8. Create the Database

Enter MySQL:

```bash
sudo mysql
```

Create the database:

```sql
CREATE DATABASE loginapp;
```

Check:

```sql
SHOW DATABASES;
```

---

# 9. Create the Application User

The Public EC2 private IP is:

```text
123.11.1.25
```

Inside MySQL:

```sql
CREATE USER 'appuser'@'123.11.1.25' IDENTIFIED BY 'YOUR_DB_PASSWORD';
```

Grant permissions:

```sql
GRANT ALL PRIVILEGES ON loginapp.* TO 'appuser'@'123.11.1.25';
```

Apply:

```sql
FLUSH PRIVILEGES;
```

Check:

```sql
SELECT user, host FROM mysql.user WHERE user = 'appuser';
```

---

# 10. Create the Users Table

```sql
USE loginapp;
```

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100),
    password VARCHAR(100)
);
```

Check:

```sql
SHOW TABLES;
```

Check structure:

```sql
DESCRIBE users;
```

---

# 11. Test MySQL

Insert test data:

```sql
INSERT INTO users (username, password)
VALUES ('manoj', '1234');
```

Check:

```sql
SELECT * FROM users;
```

Example:

```text
+----+----------+----------+
| id | username | password |
+----+----------+----------+
|  1 | manoj    | 1234     |
+----+----------+----------+
```

Exit:

```sql
exit;
```

---

# 12. Configure MySQL Remote Access

On the Private EC2:

```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
```

Change:

```text
bind-address = 127.0.0.1
```

to:

```text
bind-address = 0.0.0.0
```

Save:

```text
Ctrl + O
Enter
Ctrl + X
```

Restart:

```bash
sudo systemctl restart mysql
```

Check port:

```bash
sudo ss -lntp | grep 3306
```

Expected:

```text
0.0.0.0:3306
```

---

# 13. Create the PHP Application

On the Public EC2:

```bash
cd /var/www/html
```

Create:

```bash
sudo nano index.php
```

Use:

```php
<?php
$db_host = "123.11.1.49";
$db_name = "loginapp";
$db_user = "appuser";
$db_pass = "YOUR_DB_PASSWORD";

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    $stmt = $conn->prepare(
        "INSERT INTO users (username, password) VALUES (?, ?)"
    );
    $stmt->bind_param("ss", $username, $password);

    if ($stmt->execute()) {
        echo "<h2>Registration successful!</h2>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login Application</title>
</head>
<body>

<h1>Login Application</h1>

<form method="POST">
    <label>Username:</label>
    <input type="text" name="username" required>

    <br><br>

    <label>Password:</label>
    <input type="password" name="password" required>

    <br><br>

    <button type="submit">Save</button>
</form>

</body>
</html>

<?php
$conn->close();
?>
```

Save:

```text
Ctrl + O
Enter
Ctrl + X
```

Restart Apache:

```bash
sudo systemctl restart apache2
```

---

# 14. Test Public to Private Connection

On Public EC2:

```bash
nc -zv 123.11.1.49 3306
```

Expected:

```text
Connection to 123.11.1.49 3306 port [tcp/mysql] succeeded!
```

---

# 15. Test PHP

```bash
curl http://localhost/index.php
```

Check the web files:

```bash
ls -l /var/www/html/
```

Expected:

```text
index.php
```

---

# 16. Open the Website

Find the **Public IPv4 address** of the Public EC2 in AWS.

Open:

```text
http://YOUR-PUBLIC-IP/
```

The browser should show:

```text
Login Application

Username: [          ]

Password: [          ]

[ Save ]
```

---

# 17. Test the Application

Enter a username and password, for example:

```text
Username: manoj
Password: 1234
```

Click:

```text
Save
```

Expected:

```text
Registration successful!
```

---

# 18. Verify Data in MySQL

On Private EC2:

```bash
sudo mysql
```

Then:

```sql
USE loginapp;
```

```sql
SELECT * FROM users;
```

Example:

```text
+----+----------+----------+
| id | username | password |
+----+----------+----------+
|  1 | manoj    | 1234     |
+----+----------+----------+
```

This confirms:

```text
Browser
  -> Apache
  -> PHP
  -> Private EC2
  -> MySQL
  -> loginapp
  -> users
```

---

# 19. Push the Project to GitHub

Repository:

```text
https://github.com/Manoj111846/aws-ec2-mysql-login-app.git
```

On Public EC2:

```bash
cd /var/www/html
```

Configure Git:

```bash
git config --global user.name "Manoj Kommu"
git config --global user.email "kommumanoj134@gmail.com"
```

Initialize:

```bash
git init
```

Create `.gitignore`:

```bash
sudo nano .gitignore
```

Add:

```text
.env
*.db
*.pem
*.key
```

Save:

```text
Ctrl + O
Enter
Ctrl + X
```

Add files:

```bash
git add .
```

Commit:

```bash
git commit -m "Add AWS EC2 PHP MySQL application"
```

Connect GitHub:

```bash
git remote add origin https://github.com/Manoj111846/aws-ec2-mysql-login-app.git
```

Set main branch:

```bash
git branch -M main
```

Push:

```bash
git push -u origin main
```

For HTTPS authentication:

```text
Username: Manoj111846
Password: GitHub Personal Access Token
```

---

# 20. Final GitHub Structure

```text
aws-ec2-mysql-login-app/
|
├── index.php
└── .gitignore
```

---

# 21. Final Result

The completed project demonstrates:

- AWS VPC
- Public subnet
- Private subnet
- Internet Gateway
- NAT Gateway
- Route tables
- Security Groups
- Public EC2
- Private EC2
- Apache
- PHP
- MySQL
- PHP-to-MySQL connectivity
- HTML form submission
- MySQL data storage
- Git
- GitHub deployment

## Security Note

Do not commit real passwords, AWS private keys, `.pem` files, `.key` files, or other secrets to GitHub. For a real application, use environment variables or a secrets-management service for database credentials and securely hash user passwords instead of storing them as plain text.


---

# 22. Screenshots

## Web Application

![Web Application](readme_screenshots/webpage.png)

## MySQL Database Result

![MySQL Database Result](readme_screenshots/mysql-result.png)

> To display these images on GitHub, upload the `readme_screenshots` folder along with `README.md`.
