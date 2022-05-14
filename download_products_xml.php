<?php
	
	$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
	$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
	//отключаем статистику
	define("NO_KEEP_STATISTIC", true);
	require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
	//пространство имен
	use Bitrix\Highloadblock\HighloadBlockTable as HLBT;
	//id highload блока 
	const MY_HL_BLOCK_ID = 3;
	//подключаем модуль highload для работы с ним
	CModule::IncludeModule('highloadblock');
	//получаем экземпляр класса
	function GetEntityDataClass($HlBlockId) {
		if (empty($HlBlockId) || $HlBlockId < 1)
		{
			return false;
		}
		$hlblock = HLBT::getById($HlBlockId)->fetch();	
		$entity = HLBT::compileEntity($hlblock);
		$entity_data_class = $entity->getDataClass();
		return $entity_data_class;
	}
	
	//разбираем xml документ
	$xml = new XMLReader();
    $xml->open('https://www.galacentre.ru/download/yml/MSK-posuda.xml');
	$i = 0;
	//все товары записываем в массив
	$data = array();
	while($xml->read()) {
        if($xml->nodeType == XMLReader::ELEMENT) {
            if($xml->localName == 'offer') {
                $data[$i]['id'] = $xml->getAttribute('id');
				$xml->read();
				while ($xml->name != 'offer') {
					$xml->read();
					if($xml->nodeType == XMLReader::ELEMENT) {
						switch ($xml->localName) {
							case 'url':
								$xml->read();
								$data[$i]['url'] = $xml->value;
								break;
							case 'price':
								$xml->read();
								$data[$i]['price'] = $xml->value;
								break;
							case 'picture':
								$xml->read();
								$data[$i]['picture'] = $xml->value;
								break;
							case 'name':
								$xml->read();
								$data[$i]['name'] = iconv('UTF-8', 'Windows-1251', $xml->value);
								$arParams = array("replace_space"=>"-","replace_other"=>"-");
								$data[$i]['code'] = CUtil::translit($data[$i]["name"], "ru", $arParams);
								break;
						}
					}
				}
				$i++;
            }
        }
    }
	$entity_data_class = GetEntityDataClass(MY_HL_BLOCK_ID);
	
	//обновляем/добавляем товары
	foreach($data as $product) {
		$rsData = $entity_data_class::getList(array(
			'select' => array('ID'),
			'order' => array('ID' => 'ASC'),
			'limit' => '1',
			'filter' => array('UF_ID_PRED' => $product['id'])
		));
		if ($el = $rsData->fetch()) {
			$result = $entity_data_class::update($el['ID'], array(
				'UF_ID_PRED' => $product['id'],
				'UF_URL' => $product['url'],
				'UF_PRICE' => $product['price'],
				'UF_PICTURE' => $product['picture'],
				'UF_NAME' => $product['name'],
				'UF_CODE' => $product['code']
			));
		} else {
			$result = $entity_data_class::add(array(
				'UF_ID_PRED' => $product['id'],
				'UF_URL' => $product['url'],
				'UF_PRICE' => $product['price'],
				'UF_PICTURE' => $product['picture'],
				'UF_NAME' => $product['name'],
				'UF_CODE' => $product['code']
			));
		}
	}