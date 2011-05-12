<?php

//
// Implementation of UPS Rating Package XML
//
// Copyright 2011 Chase Allen James <chaseajames@gmail.com>
//
// Ported from ups_xml_gateway Ruby UPS Shipping Library
// written by Todd Willey
//
class ups_rating {

const DIMENSIONAL_VOLUME = 5184;
const DIMENSIONAL_DIVISOR = 194;
const ITEM_WEIGHT_LIMIT = 150;

const URL = "https://onlinetools.ups.com/ups.app/xml/Rate";
const TESTING_URL = "https://wwwcie.ups.com/ups.app/xml/Rate";

const XPCI_VERSION = "1.0";

public static $PICKUP_TYPES = array(
  'daily_pickup' => '01',
  'customer_counter' => '03',
  'one_time_pickup' => '06',
  'on_call_air' => '07',
  'suggested_retail_rates' => '11',
  'letter_center' => '19',
  'air_service_center' => '20'
);

public static $CUSTOMER_CLASSIFICATIONS = array(
  'wholesale' => '01',
  'occasional' => '03',
  'retail' => '04'
);

public static $WEIGHT_UNITS = array(
  'lbs' => 'LBS',
  'kgs' => 'KGS'
);

public static $LENGTH_UNITS = array(
  'in' => 'IN',
  'cm' => 'CM'
);

public static $SERVICE_TYPES = array(
  'next_day_air' => '01',
  'second_day_air' => '02',
  'ground' => '03',
  'worldwide_express' => '07',
  'worldwide_expedited' => '08',
  'standard' => '11',
  'three_day_select' => '12',
  'next_day_air_saver' => '13', // not in docs, but exists!
  'next_day_air_early' => '14',
  'worldwide_express_plus' => '54',
  'second_day_air_am' => '59',
  'saver' => '65'
);

public static $PACKAGE_TYPES = array(
  'unknown' => '00',
  'letter' => '01',
  'package' => '02',
  'tube' => '03',
  'pak' => '04',
  'small_express' => '2a',
  'medium_express' => '2b',
  'large_express' => '2c',
  'twofive_kg_box' => '24',
  'onezero_kg_box' => '25',
  'pallet' => '30'
);

public static $FUND_CODES = array(
  'cash' => '0',
  'check' => '8' // Cashier's check or money order
);

public static $DELIVERY_CONFIRMATION_CODES = array(
  'confirmation' => '1',
  'signature' => '2',
  'adult_signature' => '3'
);

public static $PICKUP_DAYS = array(
  'same_day' => '01',
  'future' => '02'
);

public static $ONCALL_METHODS =array(
  'internet' => '01',
  'phone' => '02'
);

public static $REQUIRED_OPTIONS = array(
  'ups_access_license_number',
  'ups_userid',
  'ups_password',
  'country',
  'to_country'
);

//
// API FUNCTIONS
//

// Execute RateServiceSelectionRequest
//
// Usage:
//
//  $opts = array(
//    'ups_access_license_number' => MY_LICENSE_NUMBER,
//    'ups_userid' => MY_USERID,
//    'ups_password' => MY_PASSWORD,
//    'country' => 'US',
//    'to_country' => 'US',
//    'weight' => 20
//  );
//
//  Packages are also supported:
//
//  $opts['packages'] = array(
//    array(
//      'type' => 'medium_express',
//      'weight' => 20
//    ),
//    array(
//      'type' => 'medium_express',
//      'weight' => 18
//    )
//  );
//
//  $response = ups_rating::rates($opts);
//
function rates($opts) {
  self::validate_options($opts, self::$REQUIRED_OPTIONS);
  $xml = self::access_request($opts) . self::rating_service_selection_request($opts);
  $raw_response = self::http_client_post_xml($xml, $opts);
  $response = self::parse_response($raw_response, $opts);
  return $response;
}



//
// INTERNAL FUNCTIONS
//

function validate_options($opts, $required) {
  $matching_keys = array_intersect(array_keys($opts), $required);
  if (count($matching_keys) != count($required)) {
    throw new Exception("Error: missing required options");
  }
}

function access_request($opts) {
  $doc = new DOMDocument('1.0');
  $access_request = $doc->appendChild(
    new DOMElement('AccessRequest'));
    $access_request->setAttributeNode(
      new DOMAttr('xml:lang', 'en-US'));
      $access_request->appendChild(
        new DOMElement('AccessLicenseNumber', $opts['ups_access_license_number']));
      $access_request->appendChild(
        new DOMElement('UserId', $opts['ups_userid']));
      $access_request->appendChild(
        new DOMElement('Password', $opts['ups_password']));
  return $doc->saveXML();
}

function rating_service_selection_request($opts) {
  $doc = new DOMDocument('1.0');

  $method = (isset($opts['shop']) && $opts['shop']) ? 'Shop' : 'Rate';

  $rssr = $doc->appendChild(
    new DOMElement('RatingServiceSelectionRequest'));
    $rssr->setAttributeNode(
      new DOMAttr('xml:lang', 'en-US'));
    $request = $rssr->appendChild(
      new DOMElement('Request'));
      $request->appendChild(
        new DOMElement('RequestAction', 'Rate'));

        $request->appendChild(
          new DOMElement('RequestOption', $method));
        
        $transaction_reference = $request->appendChild(
          new DOMElement('TransactionReference'));

          if (isset($opts['customer_context'])) {
            $transaction_reference->appendChild(
              new DOMElement('CustomerContext', substr($opts['customer_context'], 0, 512)));
          }

          $transaction_reference->appendChild(
            new DOMElement('XpciVersion', self::XPCI_VERSION));
          if (isset($opts['tool_version'])) {
            $transaction_reference->appendChild(
              new DOMElement('ToolVersion', $opts['tool_version']));
          }

    if (isset($opts['pickup_type'])) {
      $pickup_type = $request->appendChild(
        new DOMElement('PickupType'));
        $pickup_type->appendChild(
          new DOMElement('Code', self::$PICKUP_TYPES[$opts['pickup_type']]));
    }

    if (isset($opts['customer_classification'])) {
      $customer_classification = $request->appendChild(
        new DOMElement('CustomerClassification'));
        $customer_classification->appendChild(
          new DOMElement('Code', self::$CUSTOMER_CLASSIFICATIONS[$opts['customer_classification']]));
    }

    $shipment = $rssr->appendChild(
      new DOMElement('Shipment'));

      if (isset($opts['description'])) {
        $shipment->appendChild(
          new DOMElement('Description', substr($opts['description'], 0, 35)));
      }

      $shipper = $shipment->appendChild(
        new DOMElement('Shipper'));

        if (isset($opts['ups_account_number'])) {
          $shipper->appendChild(
            new DOMElement('ShipperNumber', $opts['ups_account_number']));
        }

        $address = $shipper->appendChild(
          new DOMElement('Address'));

          if (isset($opts['street_address1'])) {
            $address->appendChild(
              new DOMElement('AddressLine1', substr($opts['street_address1'], 0, 35)));
          }

          if (isset($opts['street_address2'])) {
            $address->appendChild(
              new DOMElement('AddressLine2', substr($opts['street_address2'], 0, 35)));
          }

          if (isset($opts['street_address3'])) {
            $address->appendChild(
              new DOMElement('AddressLine3', substr($opts['street_address3'], 0, 35)));
          }

          if (isset($opts['city'])) {
            $address->appendChild(
              new DOMElement('City', substr($opts['city'], 0, 30)));
          }

          if (isset($opts['state'])) {
            $address->appendChild(
              new DOMElement('StateProvinceCode', substr($opts['state'], 0, 5)));
          }

          if (isset($opts['postal_code'])) {
            $address->appendChild(
              new DOMElement('PostalCode', substr($opts['postal_code'], 0, 9)));
          }

          $address->appendChild(
            new DOMElement('CountryCode', strtoupper(substr($opts['country'], 0, 2))));

      $ship_to = $shipment->appendChild(
        new DOMElement('ShipTo'));

        if (isset($opts['recipient_number'])) {
          $ship_to->appendChild(
            new DOMElement('ShipperAssignedIdentificationNumber', $opts['recipient_number']));
        }

        if (isset($opts['company_name'])) {
          $ship_to->appendChild(
            new DOMElement('CompanyName', $opts['company_name']));
        }

        if (isset($opts['attention_name'])) {
          $ship_to->appendChild(
            new DOMElement('CompanyName', $opts['attention_name']));
        }

        if (isset($opts['phone'])) {
          $ship_to->appendChild(
            new DOMElement('PhoneNumber', $opts['phone']));
        } elseif (isset($opts['structured_phone'])) {
          $phone_number = $ship_to->appendChild(
            new DOMElement('PhoneNumber'));

            if (isset($opts['structured_phone']['country_code'])) {
              $phone_number->appendChild(
                new DOMElement('PhoneCountryCode', substr($opts['structured_phone']['country_code'], 0, 3)));
            }

            $phone_number->appendChild(
              new DOMElement('PhoneDialPlanNumber', substr($opts['structured_phone']['plan'], 0, 15)));

            $phone_number->appendChild(
              new DOMElement('PhoneLineNumber', substr($opts['structured_phone']['line'], 0, 15)));

            if (isset($opts['structured_phone']['extension'])) {
              $phone_number->appendChild(
                new DOMElement('PhoneExtension', substr($opts['structured_phone']['extension'], 0, 4)));
            }
        } // end PhoneNumber

        if (isset($opts['tax_id'])) {
          $ship_to->appendChild(
            new DOMElement('TaxIdentificationNumber', substr($opts['tax_id'], 0, 15)));
        }

        if (isset($opts['fax'])) {
          $ship_to->appendChild(
            new DOMElement('FaxNumber', substr($opts['fax'], 0, 15)));
        }

        $address = $ship_to->appendChild(
          new DOMElement('Address'));

          if (isset($opts['to_street_address1'])) {
            $address->appendChild(
              new DOMElement('AddressLine1', substr($opts['to_street_address1'], 0, 35)));
          }

          if (isset($opts['to_street_address2'])) {
            $address->appendChild(
              new DOMElement('AddressLine2', substr($opts['to_street_address2'], 0, 35)));
          }

          if (isset($opts['to_street_address3'])) {
            $address->appendChild(
              new DOMElement('AddressLine3', substr($opts['to_street_address3'], 0, 35)));
          }

          if (isset($opts['to_city'])) {
            $address->appendChild(
              new DOMElement('City', substr($opts['to_city'], 0, 30)));
          }

          if (isset($opts['to_state'])) {
            $address->appendChild(
              new DOMElement('StateProvinceCode', $opts['to_state']));
          }

          if (isset($opts['to_postal_code'])) {
            $address->appendChild(
              new DOMElement('PostalCode', $opts['to_postal_code']));
          }

          $address->appendChild(
            new DOMElement('CountryCode', substr($opts['to_country'], 0, 2)));

          if (isset($opts['to_residence'])) {
            $address->appendChild(
              new DOMElement('ResidentialAddressIndicator'));
          }

          // end Address

      // end ShipTo

      if (isset($opts['ship_from_country'])) {
        $ship_from = $shipment->appendChild(
          new DOMElement('ShipFrom'));

          if (isset($opts['recipient_number'])) {
            $ship_from->appendChild(
              new DOMElement('ShipperAssignedIdentificationNumber', $opts['recipient_number']));
          }

          if (isset($opts['company_name'])) {
            $ship_from->appendChild(
              new DOMElement('CompanyName', $opts['company_name']));
          }

          if (isset($opts['attention_name'])) {
            $ship_from->appendChild(
              new DOMElement('CompanyName', $opts['attention_name']));
          }

          if (isset($opts['phone'])) {
            $ship_from->appendChild(
              new DOMElement('PhoneNumber', $opts['phone']));
          } elseif (isset($opts['structured_phone'])) {
            $phone_number = $ship_from->appendChild(
              new DOMElement('PhoneNumber'));

              if (isset($opts['structured_phone']['country_code'])) {
                $phone_number->appendChild(
                  new DOMElement('PhoneCountryCode', substr($opts['structured_phone']['country_code'], 0, 3)));
              }

              $phone_number->appendChild(
                new DOMElement('PhoneDialPlanNumber', substr($opts['structured_phone']['plan'], 0, 15)));

              $phone_number->appendChild(
                new DOMElement('PhoneLineNumber', substr($opts['structured_phone']['line'], 0, 15)));

              if (isset($opts['structured_phone']['extension'])) {
                $phone_number->appendChild(
                  new DOMElement('PhoneExtension', substr($opts['structured_phone']['extension'], 0, 4)));
              }
          } // end PhoneNumber

          if (isset($opts['tax_id'])) {
            $ship_from->appendChild(
              new DOMElement('TaxIdentificationNumber', substr($opts['tax_id'], 0, 15)));
          }

          if (isset($opts['fax'])) {
            $ship_from->appendChild(
              new DOMElement('FaxNumber', substr($opts['fax'], 0, 15)));
          }

          $address = $ship_from->appendChild(
            new DOMElement('Address'));

            if (isset($opts['street_address1'])) {
              $address->appendChild(
                new DOMElement('AddressLine1', substr($opts['street_address1'], 0, 35)));
            }

            if (isset($opts['street_address2'])) {
              $address->appendChild(
                new DOMElement('AddressLine2', substr($opts['street_address2'], 0, 35)));
            }

            if (isset($opts['street_address3'])) {
              $address->appendChild(
                new DOMElement('AddressLine3', substr($opts['street_address3'], 0, 35)));
            }

            if (isset($opts['city'])) {
              $address->appendChild(
                new DOMElement('City', substr($opts['city'], 0, 30)));
            }

            if (isset($opts['state'])) {
              $address->appendChild(
                new DOMElement('StateProvinceCode', $opts['state']));
            }

            if (isset($opts['postal_code'])) {
              $address->appendChild(
                new DOMElement('PostalCode', $opts['postal_code']));
            }

            $address->appendChild(
              new DOMElement('CountryCode', substr($opts['country'], 0, 2)));

            if (isset($opts['from_residence'])) {
              $address->appendChild(
                new DOMElement('ResidentialAddressIndicator'));
            }

            // end Address

          // end ShipFrom

        } // end if ship_from_country  

        if ($method == 'Rate') {
          $service = $shipment->appendChild(
            new DOMElement('Service'));

            $service_type = (isset($opts['service_type']) ? $opts['service_type'] : 'ground');

            $service->appendChild(
              new DOMElement('Code', self::$SERVICE_TYPES[$service_type]));
        }

        if (isset($opts['documents_only'])) {
          $shipment->appendChild(
            new DOMElement('DocumentsOnly'));
        }

        if (isset($opts['packages'])) {
          foreach ($opts['packages'] as $pkg) {
            $package = $shipment->appendChild(
              new DOMElement('Package'));

              $packaging_type = $package->appendChild(
                new DOMElement('PackagingType'));

                $type = (isset($pkg['type']) ? $pkg['type'] : 'package');

                $packaging_type->appendChild(
                  new DOMElement('Code', self::$PACKAGE_TYPES[$type]));

                if (isset($pkg['packaging_description'])) {
                  $packaging_type->appendChild(
                    new DOMElement('Description', substr($pkg['packaging_description'], 0, 35)));
                }

              if (isset($pkg['description'])) {
                $package->appendChild(
                  new DOMElement('Description', substr($pkg['description'], 0, 35)));
              }

            if (!in_array($type, array('letter', 'tube', 'small_express', 'medium_express', 'large_express'))) {
              $dimensions = $package->appendChild(
                new DOMElement('Dimensions'));

                $unit_of_measurement = $dimensions->appendChild(
                  new DOMElement('UnitOfMeasurement'));

                  $length_unit = (isset($pkg['length_unit']) ? $pkg['length_unit'] : 'in');

                  $unit_of_measurement->appendChild(
                    new DOMElement('Code', self::$LENGTH_UNITS[$length_unit]));

                  if (isset($pkg['dimension_description'])) {
                    $unit_of_measurement->appendChild(
                      new DOMElement('Description', substr($pkg['dimension_description'], 0, 35)));
                  }

                $dimensions->appendChild(
                  new DOMElement('Length', trim(sprintf("%6.2f", floatval($pkg['length'])))));

                $dimensions->appendChild(
                  new DOMElement('Width', trim(sprintf("%6.2f", floatval($pkg['width'])))));

                $dimensions->appendChild(
                  new DOMElement('Height', trim(sprintf("%6.2f", floatval($pkg['height'])))));

            } // Dimensions

            if (isset($pkg['dimensional_weight'])) {
              $dimensional_weight = $package->appendChild(
                new DOMElement('DimensionalWeight'));

                $unit_of_measurement = $dimensional_weight->appendChild(
                  new DOMElement('UnitOfMeasurement'));

                  $dimensional_weight_unit = (isset($pkg['dimensional_weight_unit']) ? $pkg['dimensional_weight_unit'] : 'lbs');

                  $unit_of_measurement->appendChild(
                    new DOMElement('Code', self::$WEIGHT_UNITS[$dimensional_weight_unit]));

                    if (isset($pkg['dimensional_weight_description'])) {
                      $unit_of_measurement->appendChild(
                        new DOMElement('Description', substr($pkg['dimensional_weight_description'], 0, 35)));
                    }

                    $unit_of_measurement->appendChild(
                      new DOMElement('Weight', trim(sprintf("%6.1f", floatval($pkg['dimensional_weight'])))));
            } // DimensionalWeight

            if (isset($pkg['weight'])) {
              $package_weight = $package->appendChild(
                new DOMElement('PackageWeight'));

                $weight_unit = (isset($pkg['weight_unit']) ? $pkg['weight_unit'] : 'lbs');

                $package_weight->appendChild(
                  new DOMElement('Code', self::$WEIGHT_UNITS[$weight_unit]));

                  if (isset($pkg['weight_description'])) {
                    $package_weight->appendChild(
                      new DOMElement('Description', substr($pkg['weight_description'], 0, 35)));
                  }

                  $package_weight->appendChild(
                    new DOMElement('Weight', trim(sprintf("%6.1f", floatval($pkg['weight'])))));
            } // Weight

            if (isset($pkg['large_package'])) {
              $package->appendChild(
                new DOMElement('LargePackageIndicator'));
            }

            if (isset($pkg['cod_value']) ||
                isset($pkg['delivery_confirmation']) ||
                isset($pkg['phone']) ||
                isset($pkg['structured_phone']) ||
                isset($pkg['additional_handling'])) {

              $package_service_options = $package->appendChild(
                new DOMElement('PackageServiceOptions'));

                if (isset($pkg['cod_value'])) {
                  $cod = $package_service_options->appendChild(
                    new DOMElement('COD'));

                    if (isset($pkg['cod_fund_code'])) {
                      $cod->appendChild(
                        new DOMElement('CODFundsCode', self::$FUND_CODES[$pkg['cod_fund_code']]));
                    }

                    $cod->appendChild(
                      new DOMElement('CODCode', '3'));

                    $cod_amount = $cod->appendChild(
                      new DOMElement('CODAmount'));

                      $cod_currency_code = (isset($pkg['cod_currency_code']) ? $pkg['cod_currency_code'] : 'USD');

                      $cod_amount->appendChild(
                        new DOMElement('CurrencyCode', $cod_currency_code));

                      $cod_amount->appendChild(
                        new DOMElement('MonetaryValue', trim(sprintf("%8.2f", floatval($pkg['cod_value'])))));

                    $insured_value = $cod->appendChild(
                      new DOMElement('InsuredValue'));

                      $insured_value->appendChild(
                        new DOMElement('CurrencyCode', $cod_currency_code));

                      $insured_value->appendChild(
                        new DOMElement('MonetaryValue', trim(sprintf("%8.2f", floatval($pkg['cod_insurance'])))));

                    if (isset($pkg['cod_control_number'])) {
                      $cod->appendChild(
                        new DOMElement('ControlNumber', substr($pkg['cod_control_number'], 0, 11)));
                    }

                } // COD

                if (isset($pkg['delivery_confirmation'])) {
                  $delivery_confirmation = $package_service_options->appendChild(
                    new DOMElement('DeliveryConfirmation'));

                    $delivery_confirmation->appendChild(
                      new DOMElement('DCISType', self::$DELIVERY_CONFIRMATION_CODES[$pkg['delivery_confirmation']]));
                } // DeliveryConfirmation

                if (isset($pkg['phone']) || isset($pkg['structured_phone'])) {
                  $verbal_confirmation = $package_service_options->appendChild(
                    new DOMElement('VerbalConfirmation'));

                    if (isset($pkg['verbal_confirmation_name'])) {
                      $verbal_confirmation->appendChild(
                        new DOMElement('Name', substr($pkg['verbal_confirmation_name'], 0, 35)));
                    }

                    if (isset($pkg['phone'])) {
                      $verbal_confirmation->appendChild(
                        new DOMElement('PhoneNumber', $pkg['phone']));
                    } elseif (isset($pkg['structured_phone'])) {
                      $phone_number = $verbal_confirmation->appendChild(
                        new DOMElement('PhoneNumber'));

                        if (isset($pkg['structured_phone']['country_code'])) {
                          $phone_number->appendChild(
                            new DOMElement('PhoneCountryCode', substr($opts['structured_phone']['country_code'], 0, 3)));
                        }

                        $phone_number->appendChild(
                          new DOMElement('PhoneDialPlanNumber', substr($opts['structured_phone']['plan'], 0, 15)));

                        $phone_number->appendChild(
                          new DOMElement('PhoneLineNumber', substr($opts['structured_phone']['line'], 0, 15)));

                        if (isset($opts['structured_phone']['extension'])) {
                          $phone_number->appendChild(
                            new DOMElement('PhoneExtension', substr($opts['structured_phone']['extension'], 0, 4)));
                        }

                    } // PhoneNumber
               } // VerbalConfirmation

               if (isset($pkg['additional_handling'])) {
                 $package_service_options->appendChild(
                   new DOMElement('AdditionalHandling'));
               }

            } // if pkg services specified

          } // foreach $opts['packages']

        } else { // A list of packages was not passed in, fake one out here

          $package = $shipment->appendChild(
            new DOMElement('Package'));

            $packaging_type = $package->appendChild(
              new DOMElement('PackagingType'));

              $package_type = (isset($opts['package_type']) ? $opts['package_type'] : 'package');

              $packaging_type->appendChild(
                new DOMElement('Code', self::$PACKAGE_TYPES[$package_type]));

              $packaging_type->appendChild(
                new DOMElement('Description', '(Default) Medium Express Box'));

            $package->appendChild(
              new DOMElement('Description', 'Default Generated Package'));

            $package_weight = $package->appendChild(
              new DOMElement('PackageWeight'));

              $unit_of_measurement = $package_weight->appendChild(
                new DOMElement('UnitOfMeasurement'));

                $weight_unit = (isset($opts['weight_unit']) ? $opts['weight_unit'] : 'lbs');

                $unit_of_measurement->appendChild(
                  new DOMElement('Code', self::$WEIGHT_UNITS[$weight_unit]));

              $package_weight->appendChild(
                new DOMElement('Weight', trim(sprintf('%6.1f', floatval($opts['weight'])))));

        } // Package

        if (isset($opts['saturday_pickup']) ||
            isset($opts['cod_value']) ||
            isset($opts['on_call_day']) ||
            isset($opts['insured_value'])) {

          $package_service_options = $shipment->appendChild(
            new DOMElement('ShipmentServiceOptions'));

            if (isset($opts['saturday_pickup'])) {
              $package_service_options->appendChild(
                new DOMElement('SaturdayPickupIndicator'));
            }

            if (isset($opts['on_call_day']) || isset($opts['on_call_method'])) {
              $on_call_air = $package_service_options->appendChild(
                new DOMElement('OnCallAir'));

                $schedule = $on_call_air->appendChild(
                  new DOMElement('Schedule'));

                  $on_call_day = (isset($opts['on_call_day']) ? $opts['on_call_day'] : 'future');

                  $schedule->appendChild(
                    new DOMElement('PickupDay', self::$PICKUP_DAYS[$on_call_day]));

                  $on_call_method = (isset($opts['on_call_method']) ? $opts['on_call_method'] : 'internet');

                  $schedule->appendChild(
                    new DOMElement('Method', self::$ONCALL_METHODS[$on_call_method]));
            }

            if (isset($opts['insured_value'])) {
              $insured_value = $package_service_options->appendChild(
                new DOMElement('InsuredValue'));

                $code = (isset($opts['cod_currency_code']) ? $opts['cod_currency_code'] : 'USD');

                $insured_value->appendChild(
                  new DOMElement('CurrencyCode', $code));

                $insured_value->appendChild(
                  new DOMElement('MonetaryValue', $opts['insured_value']));
            }

            if (isset($opts['cod_value'])) {
              $cod = $package_service_options->appendChild(
                new DOMElement('COD'));

                if (isset($opts['cod_fund_code'])) {
                  $cod->appendChild(
                    new DOMElement('CODFundsCode', self::$FUND_CODES[$opts['cod_fund_code']]));
                }

                $cod->appendChild(
                  new DOMElement('CODCode', '3'));

                $cod_amount = $cod->appendChild(
                  new DOMElement('CODAmount'));

                  $code = (isset($opts['cod_currency_code']) ? $opts['cod_currency_code'] : 'USD');

                  $cod_amount->appendChild(
                    new DOMElement('CurrencyCode', $code));

                  $cod_amount->appendChild(
                    new DOMElement('MonetaryValue', $opts['cod_value']));

                $insured_value = $cod->appendChild(
                  new DOMElement('InsuredValue'));

                  $code = (isset($opts['cod_currency_code']) ? $opts['cod_currency_code'] : 'USD');

                  $insured_value->appendChild(
                    new DOMElement('CurrencyCode', $code));

                  $insured_value->appendChild(
                    new DOMElement('MonetaryValue', $opts['insured_value']));

                if (isset($opts['cod_control_number'])) {
                  $cod->appendChild(
                    new DOMElement('ControlNumber', substr($opts['cod_control_number'], 0, 11)));
                }

              } // COD

        } // if any shipping services

        if (isset($opts['negotiated_rates'])) {
          $rate_information = $shipment->appendChild(
            new DOMElement('RateInformation'));

            $rate_information->appendChild(
              new DOMElement('NegotiatedRatesIndicator'));
        }

  return $doc->saveXML();
}

function http_client_post_xml($xml, $opts) {

  $url = ((isset($opts['test']) && $opts['test']) ? self::TESTING_URL : self::URL);

  $client = curl_init($url);
  curl_setopt_array($client, array(
    CURLOPT_VERBOSE => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $xml,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HEADER => false,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: text/xml; charset=utf-8',
      'Content-Length: ' . strlen($xml)
    )
  ));

  $resp = curl_exec($client);
  curl_close($client);

  return $resp;
}

function parse_response($resp, $opts) {
  $xml = simplexml_load_string($resp);
  $response = array();
  if (empty($resp)) {
    $response['success'] = false;
    $response['error_message'] = 'Communication with UPS failed.';
  } elseif (self::is_error_response($xml)) {
    $response['success'] = false;
    $response['error_response'] = array(
      'status_code' => (string) $xml->Response->ResponseStatusCode,
      'status_description' => (string) $xml->Response->ResponseStatusDescription,
      'error' => array(
        'severity' => (string) $xml->Response->Error->ErrorSeverity,
        'code' => (string) $xml->Response->Error->ErrorCode,
        'description' => (string) $xml->Response->Error->ErrorDescription,
        'location' => (string) $xml->Response->Error->ErrorLocation->ErrorLocationElementName
      )
    );
    $response['error_message'] = $response['error_response']['status_description'];
  } else {
    $rated_shipments = $xml->xpath('//RatingServiceSelectionResponse/RatedShipment');
    $response['rates'] = array();
    foreach ($rated_shipments as $shipment) {
      $service_code = self::service_code($shipment);
      $charge = self::total_charge($shipment);
      $response['rates'][$service_code] = $charge;
    }
    $response['success'] = true;
  }
  return $response;
}

// If there is no status code or if the status code is 0, this response is an error
function is_error_response($xml) {
  $status_code = (string) $xml->Response->ResponseStatusCode;
  return (!$status_code || (intval($status_code) == 0));
}

// Get the service code from the given shipment XML
function service_code($shipment) {
  $service_code_raw = (string) $shipment->Service->Code;
  $service_code = array_search($service_code_raw, self::$SERVICE_TYPES);
  return $service_code;
}

// Get the total charges from the given shipment XML
function total_charge($shipment) {
  $total_charges_raw = (string) $shipment->TotalCharges->MonetaryValue;
  $total_charges = floatval($total_charges_raw);
  return $total_charges;
}


} // ups_rating class

?>
