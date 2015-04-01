<?php

//nusoap version ////////////////////

class Fedex_Rate_Core {

	public $serviceRateType;
	public $serviceRateCharge;

	protected $ssl_switch = TRUE; //make sure this page can only be accessed through ssl

	public function __construct()
		{
			//necessary to work with Kohana
			parent::__construct();
			
			//Kohana method of setting page to http
			Kohana::config_set('core.site_protocol', 'https'); //change this controller to ssl
		}

	public function get_rates()
		{
			//nusoap library
			require_once('nusoap.php');

			//make sure these are in the right place
			require_once('fedex-common.php');
			$path_to_wsdl = "media/wsdl/fedex/RateService_v7.wsdl";

			//initiate Fedex class instance to grab information
			$Fedex = new Fedex();

			ini_set("soap.wsdl_cache_enabled", "0");

			$client = new nusoap_client($path_to_wsdl, true);

			//calculate total package weight & value
			$line_item = $Fedex->shipment()->Package->LineItems;
			$shipment_total = "";
			$shipment_value = "";
			$package_number = 0; //internal value, not associated with fedex's

			foreach ($line_item as $item)
				{
					$shipment_total += $item['weight'] * $item['qty'];
					$shipment_value += $item['price'] * $item['qty'];
				}

			//if weight is larger than 150lbs
			if($shipment_total > 149)
				{
					$package_number = 1; //it's actually 2 packages, 1 acts as a boolean
					$shipment_total = $shipment_total / 2;
				}

			$request['WebAuthenticationDetail'] = array('UserCredential' =>
											array('Key' => $Fedex->customer_info()->Key, 'Password' => $Fedex->customer_info()->Password)); // Replace 'XXX' and 'YYY' with FedEx provided credentials
			$request['ClientDetail'] = array('AccountNumber' => $Fedex->customer_info()->AccountNumber, 'MeterNumber' => $Fedex->customer_info()->MeterNumber);// Replace 'XXX' with your account and meter number
			$request['TransactionDetail'] = array('CustomerTransactionId' => ' *** Rate Available Services Request v7 using PHP ***');
			$request['Version'] = array('ServiceId' => 'crs', 'Major' => '7', 'Intermediate' => '0', 'Minor' => '0');
			$request['RequestedShipment']['DropoffType'] = 'REGULAR_PICKUP'; // valid values REGULAR_PICKUP, REQUEST_COURIER, ...
			$request['RequestedShipment']['ShipTimestamp'] = date('c');


			// Service Type and Packaging Type are not passed in the request
			$request['RequestedShipment']['Shipper'] = array('Address' => array(
												'StreetLines' => array($Fedex->shipment()->Shipper->StreetLines), // Origin details
												'City' => $Fedex->shipment()->Shipper->City,
												'StateOrProvinceCode' => $Fedex->shipment()->Shipper->StateOrProvinceCode,
												'PostalCode' => $Fedex->shipment()->Shipper->PostalCode,
												'CountryCode' => $Fedex->shipment()->Shipper->CountryCode));


			$request['RequestedShipment']['Recipient'] = array('Address' => array (
												'StreetLines' => array($Fedex->shipment()->Customer->StreetLines), // Destination details
												'City' => $Fedex->shipment()->Customer->City,
												'StateOrProvinceCode' => $Fedex->shipment()->Customer->StateOrProvinceCode,
												'PostalCode' => $Fedex->shipment()->Customer->PostalCode,
												'CountryCode' => $Fedex->shipment()->Customer->CountryCode));

			//create array of commodity values
			//if needed

			/**foreach($line_item as $comm)
				{
					array('Commodities' => array(
					'Name' => 'Blue Hat',
					'NumberOfPieces' => '2',
					'Description' => 'Blue Baseball Hat',
					'CountryOfManufacture' => 'US',
					'Weight' => array(	'Units' => 'LB',
					'Value' => 1.00),
					'Quantity' => '2',
					'QuantityUnits' => 'EA',
					'UnitPrice' => array('Currency' => 'USD', 'Amount' => '25'),
					'CustomsValue' => array('Currency' => 'USD', 'Amount' => '50')));
				}**/

			// commodities go here
			$request['RequestedShipment']['InternationalDetail'] = array(

														'CustomsValue' => array('Currency' => 'USD', 'Amount' => $shipment_value),


														/**'Commodities' => array(
																	'Name' => 'Red Shoes',
																	'NumberOfPieces' => '2',
																	'Description' => 'Red Nike Shoes',
																	'CountryOfManufacture' => 'US',
																	'Weight' => array(	'Units' => 'LB',
																						'Value' => 2.0),
																	'Quantity' => '2',
																	'QuantityUnits' => 'EA',
																	'UnitPrice' => array('Currency' => 'USD', 'Amount' => '25'),
																	'CustomsValue' => array('Currency' => 'USD', 'Amount' => '50')),

														'Commodities' => array(
																	'Name' => 'Black Coat',
																	'NumberOfPieces' => '1',
																	'Description' => 'Black Wool Coat',
																	'CountryOfManufacture' => 'US',
																	'Weight' => array(	'Units' => 'LB',
																						'Value' => 6.0),
																	'Quantity' => '1',
																	'QuantityUnits' => 'EA',
																	'UnitPrice' => array('Currency' => 'USD', 'Amount' => '250'),
																	'CustomsValue' => array('Currency' => 'USD', 'Amount' => '250'))
															**/			);


			$request['RequestedShipment']['ShippingChargesPayment'] = array('PaymentType' => 'SENDER',
											'Payor' => array('AccountNumber' => $Fedex->customer_info()->AccountNumber,
											'CountryCode' => 'US'));
			$request['RequestedShipment']['RateRequestTypes'] = 'ACCOUNT';
			$request['RequestedShipment']['RateRequestTypes'] = 'LIST';
			$request['RequestedShipment']['PackageCount'] = $Fedex->shipment()->Package->Count;
			$request['RequestedShipment']['PackageDetail'] = $Fedex->shipment()->Package->Detail;
			$request['RequestedShipment']['RequestedPackageLineItems'] = array(
												'0' =>
													array('InsuredValue' => array('Currency' => 'USD', 'Amount' => $shipment_value),
														'Weight' => array('Value' => $shipment_total,
														'Units' => 'LB'))
														);


			//////////////////////////////////////////////////////////
			/// Template for using dimensions or multiple packages ///
			//////////////////////////////////////////////////////////

			/*'1' => array('Weight' => array('Value' => 5.0,
							  'Units' => 'LB'),
							  'Dimensions' => array('Length' => 20,
							  'Width' => 20,
							  'Height' => 10,
							  'Units' => 'IN')));*/

			try
			{
				//this is looking for "getRates" at the bottom of the wsdl file
				$response = $client->call('getRates', array('RateRequest' => $request));

				if ($client->fault)
					{
						echo '<h2>Fault</h2><pre>'; print_r($response); echo '</pre>';
					}
				else
					{
						$err = $client->getError();

						if ($err)
							{
								echo '<h2>Error</h2><pre>' . $err . '</pre>';
							}
						else
							{
							///////////////////////////////////////////////////
							////GET DATA TOGETHER TO ECHO ON SCREEN  /////////
							/////////////////////////////////////////////////

								if ($response['HighestSeverity'] == "ERROR")
									{
										$this->reply_status = "fail";
										return $this;
									}
								else
									{
										$this->reply_status = "pass";

										//count the number of service types returned
										$responseNum = count($response['RateReplyDetails']);

										//setup data array to send to view
										$serviceRateType = array();
										$serviceRateCharge = array();

										//loop through and populate arrays
										for($i=0;$i<$responseNum;$i++)
											{
												$reply_arr = $response['RateReplyDetails'];
												$reply = $reply_arr[$i];

												$serviceType = $reply['ServiceType'];
												$charge = $reply['RatedShipmentDetails'][0]['ShipmentRateDetail']['TotalNetCharge']['Amount'];

												$charge = $charge * 1.2; //add 20% to base rate as handling charge

												if ($charge < 8.0)
													{
														$charge = 8.0; //lowest shipping fee is $8, set to whatever you want
													}

												//include commodities here

												//if number of packages is 2, multiply costs
												if ($package_number == 1)
													{
														$serviceRateCharge[] = $charge * 2;
													}
												else
													{
														$serviceRateCharge[] = $charge;
													}

												$serviceRateType[] = $serviceType;
											}

										$this->serviceRateType = $serviceRateType;
										$this->serviceRateCharge = $serviceRateCharge;
										$this->serviceCountry = $Fedex->shipment()->Customer->CountryCode;
										$this->weight = $shipment_total;

										return $this;
									}

									/*

									//this is for testing purposes and shouldn't be used directly in production
									//It could be modified for error logging or other error handling procedure

									else
									{
										//create error array
										$serviceError = array();

										//if there is an error send it instead of rates
										foreach ($response -> Notifications as $notification)
										{
											if(is_array($response -> Notifications))
											{
												$serviceError[] = $notification -> Severity . ": " . $notification -> Message;
											}
											else
											{
												$serviceError[] = $notification;
											}
										}

										$this->serviceError = $serviceError;
									}

									return $this;

									writeToLog($client);    // Write to log file
								*/
							}
					}

			} catch (SoapFault $exception) {
			   printFault($exception, $client);
			}

		} //end method

} //end class

?>
