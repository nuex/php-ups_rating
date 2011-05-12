# php-ups_rating

PHP implementation of UPS Rating XML API.

## USAGE

    $opts = array(
      'ups_access_license_number' => MY_LICENSE_NUMBER,
      'ups_userid' => MY_USERID,
      'ups_password' => MY_PASSWORD,
      'country' => 'US',
      'to_country' => 'US',
      'weight' => 20
    );
  
    Packages are also supported:
  
    $opts['packages'] = array(
      array(
        'type' => 'medium_express',
        'weight' => 20
      ),
      array(
        'type' => 'medium_express',
        'weight' => 18
      )
    );
  
    $response = ups_rating::rates($opts);
  
