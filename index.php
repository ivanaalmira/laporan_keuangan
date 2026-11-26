<?php
require 'config.php';
redirect(user() ? 'dashboard.php' : 'login.php');
