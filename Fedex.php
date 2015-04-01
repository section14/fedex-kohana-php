<?php

class Fedex_Core
	{
		public function customer_info()
			{
				//this data comes from Fedex
				$this->Key = "your key";
				$this->Password = "your password";
				$this->AccountNumber = "your account number";
				$this->MeterNumber = "your meter number";

				//who's paying the charges
				$this->PaymentType = "SENDER";
				$this->CountryCode = "US";

				return $this;
			}

		public function shipment()
			{
				$this->ServiceType = "";
				$this->Packaging = "YOUR_PACKAGING";

				//shipper address info
				$this->Shipper->StreetLines = "1234 Main St";
				$this->Shipper->City = "Anytown";
				$this->Shipper->StateOrProvinceCode = "PA";
				$this->Shipper->PostalCode = "15202";
				$this->Shipper->CountryCode = "US";

				//customer address info
				$this->Customer->StreetLines = $_SESSION['cart']['ship']['address_1'];
				$this->Customer->City = $_SESSION['cart']['ship']['city'];
				$this->Customer->StateOrProvinceCode = $_SESSION['cart']['ship']['state'];
				$this->Customer->PostalCode = $_SESSION['cart']['ship']['postal_code'];
				$this->Customer->CountryCode = country::code($_SESSION['cart']['ship']['country'], "iso_2");

				//array to grab product information
				$package = array();

				//products stored in session, can be changed to suit your needs
				$products = $_SESSION['cart']['products']; 

				//add weight for each part number to array
				foreach ($products as $product)
					{
						//Kohana 2.3.4 ORM library reference
						$prod = ORM::factory("product")->find($product['prod_id']);

						//Fedex needs product name, weight, qty, and price. However you want
						//to get it into these variables is up to you. This could be modified
						//to use dimensions as well
						$package[$prod->id]['prod_name'] = $prod->prod_name;
						$package[$prod->id]['weight'] = $prod->weight;
						$package[$prod->id]['qty'] = $_SESSION['cart']['products'][$prod->id]['qty'];
						$package[$prod->id]['price'] = $_SESSION['cart']['products'][$prod->id]['price'];
					}

				//array or packages weight, dimensions, etc
				$this->Package->LineItems = $package;

				//package info
				$this->Package->Count = "1"; // to make 2 or more packages, exact calculations are needed
				$this->Package->Detail = "INDIVIDUAL_PACKAGES"; // or PACKAGE SUMMARY

				return $this;
			}

		public function nameChange($service, $country)
			{
				//ONLY services listed below will be offered
				//update list if additional services are needed
				//or comment out what you don't need

				switch ($service)
					{
						case "FIRST_OVERNIGHT";
						$newName = "First Overnight (by 8:00 am)";
						break;

						case "PRIORITY_OVERNIGHT";
						$newName = "Priority Overnight (by 10:30 am)";
						break;

						case "STANDARD_OVERNIGHT";
						$newName = "Standard Overnight (next business day)";
						break;

						case "FEDEX_2_DAY";
						$newName = "Fedex 2 Day Air (2 business days)";
						break;

						case "FEDEX_EXPRESS_SAVER";
						$newName = "Fedex Express Saver (3 business days)";
						break;

						case "FEDEX_GROUND";
						if($country == "US")
							{
								$newName = "Fedex Ground (1-5 business days)";
							}
						else
							{
								$newName = "Fedex Ground (3-7 business days)";
							}
						break;

						case "INTERNATIONAL_PRIORITY";
						$newName = "Fedex International Priority (1-3 business days)";
						break;

						case "INTERNATIONAL_ECONOMY";
						$newName = "Fedex International Economy (2-5 business days)";
						break;

						default:
						$newName = "NOT_OFFERED";
					}

				return $newName;
			}

	}

?>
