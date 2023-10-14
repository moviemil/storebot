<?php
$token = '6661652395:AAHfT1yL4QePWkUJ5_Z6GyTTse5-TQ9oPlk';
$apiUrl = "https://api.telegram.org/bot$token";
$appUrl = 'https://storebot.vercel.app/api';

function setWebhook() {
  global $apiUrl, $appUrl;
  $url = $apiUrl . '/setWebhook?url=' . $appUrl;
  $res = file_get_contents($url);
  echo $res;
}

function checkWebhookStatus() {
  $webhookInfo = getWebhookInfo();
  $statusMessage = $webhookInfo ? "Webhook está ativo" : "Webhook não está configurado";
  return $statusMessage;
}

function getWebhookInfo() {
  global $apiUrl;
  $url = $apiUrl . '/getWebhookInfo';
  $response = file_get_contents($url);
  $data = json_decode($response, true);
  return $data['ok'] ? $data['result'] : null;
}

function getDataFromSheet() {
  // Substitua 'ID_DA_SUA_PLANILHA' pelo ID da sua planilha do Google Sheets
  $spreadsheetId = '1ygOrIsULzQ_kqcHOrE9fQy02aif4Q44Q_G_FXmkqZFQ';
  $sheetName = 'Dados';
  $data = file_get_contents("https://docs.google.com/spreadsheets/u/1/d/$spreadsheetId/gviz/tq?tqx=out:csv&sheet=$sheetName");
  $lines = explode(PHP_EOL, $data);
  $header = str_getcsv(array_shift($lines));
  $jsonData = [];

  foreach ($lines as $line) {
    $row = str_getcsv($line);
    $rowData = array_combine($header, $row);
    $jsonData[] = $rowData;
  }

  return json_encode($jsonData, JSON_PRETTY_PRINT);
}

$update = file_get_contents("php://input");
$update = json_decode($update, true);

if (isset($update['message'])) {
  $message = $update['message'];
  $chatId = $message['chat']['id'];
  $messageText = $message['text'];

  if (strpos($messageText, '/start') === 0) {
    $response = "Bem-vindo ao bot!\n\n";
    $response .= "Você pode usar os seguintes comandos:\n";
    $response .= "/psv - Pesquisar jogos na planilha\n";
    $response .= "/addgrupo - Adicionar ao grupo\n";
  } else if (strpos($messageText, '/psv') === 0) {
    $searchTerm = trim(str_replace('/psv', '', $messageText));
    $jsonData = getDataFromSheet();
    $data = json_decode($jsonData, true);
    $results = [];

    foreach ($data as $game) {
      if (isset($game['nome']) && stripos($game['nome'], $searchTerm) !== false) {
        $results[] = $game;
      }
    }

    $response = "Resultados encontrados:\n\n";

    if (!empty($results)) {
      foreach ($results as $result) {
        $response .= "Nome: " . $result['nome'] . "\n";
        $response .= "Download Pkg: " . $result['game'] . "\n";
        $response .= "Download WORK: " . $result['work'] . "\n";
        $response .= "-----------\n";
      }
    } else {
      $response = "Nenhum jogo encontrado para: $searchTerm";
    }
  } else if (strpos($messageText, '/addgrupo') === 0) {
    $groupLink = trim(str_replace('/addgrupo', '', $messageText));
    $response = joinGroup($groupLink, $chatId);
  } else {
    $response = "Desculpe, comando não reconhecido. Use /start para obter ajuda.";
  }
} else {
  $response = "Desculpe, ocorreu um erro no processamento da mensagem.";
}

if ($chatId) {
  $sendMessageUrl = $apiUrl . "/sendMessage?chat_id=$chatId&text=" . urlencode($response);
  file_get_contents($sendMessageUrl);
}

function joinGroup($groupLink, $chatId) {
  $response = "Tentando ingressar no grupo...";

  if (strpos($groupLink, 'https://t.me/') === 0) {
    $groupLink = str_replace('https://t.me/', '', $groupLink);
  }

  if ($chatId) {
    $inviteUrl = $apiUrl . "/inviteChat?chat_id=$chatId&invite_link=$groupLink";
    $result = file_get_contents($inviteUrl);

    if ($result === 'true') {
      $response = "Você foi adicionado com sucesso ao grupo!";
    } else {
      $response = "Desculpe, não foi possível adicionar você ao grupo.";
    }
  } else {
    $response = "Desculpe, não foi possível encontrar o grupo.";
  }

  return $response;
}
setWebhook();
