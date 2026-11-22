<?php

function is_valid_email($email, $check_dns = false)
{

	$pattern = '/^[a-z0-9!#$%&\'*+\/=?^_`{|}~.-]+@[a-z0-9-]+(\.[a-z0-9-]+)*/';
	switch (true) {
		case false === filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE):
		case !preg_match($pattern, $email):
		case !preg_match('/@([^@\[]++)\z/', $email, $m):
		return false;

		case !$check_dns:
		case checkdnsrr($m[1], 'MX'):
		case checkdnsrr($m[1], 'A'):
		case checkdnsrr($m[1], 'AAAA'):
		return true;

		default:
		return false;
	}
}
