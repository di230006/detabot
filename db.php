<?php

$conn = mysqli_connect('localhost', 'root', '', 'detabot');

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}
