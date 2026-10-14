<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    /* 도커 컨테이너 안에서 내 컴퓨터를 찾아가게 만드는 주소(도메인) 
    $host = 'host.docker.internal';
    $port = '3306';
    $dbname = 'board';
    $username = 'root';
    $password = 'bdh841012*';
    */
    $host = 'host.docker.internal';
    $port = '3306';
    $dbname = 'board';
    $username = 'root';
    $password = 'bdh841012*';
    // 실서버용
    // $host = '127.0.0.1';
    // $port = '3306';
    // $dbname = '';
    // $username = 'dh';
    // $password = 'bdh841012*';

    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("<h1 style='color: red;'> DB 연결실패: " . $e->getMessage() . "</h1>");
    }

    /*

    CREATE TABLE php_board(
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(100),
        content TEXT,
        writer VARCHAR(50),
        filename TEXT,
        filepath TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    */
?>