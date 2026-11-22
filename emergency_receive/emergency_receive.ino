
#include <ESP8266WiFi.h>
#include <WiFiClientSecure.h>
#include <ESP8266HTTPClient.h>
#include <ArduinoJson.h>
#include <U8g2lib.h>
bool callActive = false;
unsigned long currentCallId = 0;

#define BUTTON_PIN D2

int lastButtonReading = HIGH;
int buttonState = HIGH;
unsigned long lastButtonChange = 0;

const unsigned long DEBOUNCE_TIME = 30;

// --------------------------
// Wi-Fi設定
// --------------------------
const char* WIFI_SSID = "JCOM_BCWL";
const char* WIFI_PASSWORD = "304265353114";

// --------------------------
// サーバー設定
// --------------------------
const char* API_URL =
  "https://bananagunso-dev.f5.si/emergency/api/status.php";

const char* API_TOKEN = "3f577b6ada3195d981522245086b57e5b0baf59cf9cc6c766bd9f9ae01179840";

// --------------------------
// OLED設定（動作確認済みの配線）
// --------------------------
#define OLED_RESET U8X8_PIN_NONE
#define OLED_SCL 12
#define OLED_SDA 14
#define BUZZER_PIN D1

U8G2_SSD1306_128X64_NONAME_F_HW_I2C
oled(U8G2_R0, OLED_RESET, OLED_SCL, OLED_SDA);

// --------------------------
unsigned long lastPoll = 0;
const unsigned long POLL_INTERVAL = 5000;

// --------------------------
// OLED表示
// --------------------------
void showStatus(
  const char* status,
  const char* sender,
  unsigned long callId,
  const char* server) {
  oled.clearBuffer();

  // 黄色領域
  oled.setFont(u8g2_font_6x10_tf);
  oled.drawStr(0, 10, "Emergency System");

  // 青色領域
  oled.setFont(u8g2_font_10x20_tf);
  oled.drawStr(0, 36, status);

  oled.setFont(u8g2_font_5x8_tf);

  char line[32];

  snprintf(line, sizeof(line), "From: %.15s", sender);
  oled.drawStr(0, 49, line);

  snprintf(line, sizeof(line), "ID: %lu  %s",
           callId, server);
  oled.drawStr(0, 61, line);

  oled.sendBuffer();
}

// --------------------------
// Wi-Fi接続
// --------------------------

void connectWiFi() {
  WiFi.mode(WIFI_STA);
  WiFi.disconnect();
  delay(500);

  Serial.println("Connecting to WiFi...");

  uint8_t bssid[] = {
  0x00, 0xEB, 0xD8, 0x09, 0x99, 0x81
};

  WiFi.begin(WIFI_SSID, WIFI_PASSWORD, 11, bssid);

  unsigned long start = millis();

  while (WiFi.status() != WL_CONNECTED && millis() - start < 20000) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();

  Serial.print("WiFi status: ");
  Serial.println(WiFi.status());

  Serial.print("SSID: ");
  Serial.println(WiFi.SSID());

  Serial.print("BSSID: ");
  Serial.println(WiFi.BSSIDstr());

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected");
    Serial.print("IP: ");
    Serial.println(WiFi.localIP());

    showStatus("Connected", "---", 0, "WiFi OK");
  } else {
    Serial.println("WiFi connection failed");
    showStatus("WiFi Failed", "---", 0, "Check WiFi");
  }
}

// --------------------------
// サーバー状態取得
// --------------------------
void checkServer() {
  if (WiFi.status() != WL_CONNECTED) {
    showStatus("WiFi Error", "---", 0, "Offline");
    return;
  }

  WiFiClientSecure client;

  // 初期動作確認用。後で証明書検証を設定する。
  client.setInsecure();

  HTTPClient http;
  http.setTimeout(5000);

  if (!http.begin(client, API_URL)) {
    showStatus("HTTP Error", "---", 0, "Begin");
    return;
  }

  http.addHeader(
    "Authorization",
    String("Bearer ") + API_TOKEN);

  int httpCode = http.GET();

  if (httpCode != HTTP_CODE_OK) {
    char errorText[24];
    snprintf(errorText, sizeof(errorText),
             "HTTP %d", httpCode);

    showStatus("Server Error", "---", 0, errorText);

    Serial.println(errorText);
    http.end();
    return;
  }

  String payload = http.getString();
  http.end();

  JsonDocument doc;

  DeserializationError error =
    deserializeJson(doc, payload);

  if (error) {
    showStatus("JSON Error", "---", 0, "Parse");
    Serial.println(error.c_str());
    return;
  }

  callActive  = doc["state"]["active"] | false;
  const char* sender =
    doc["state"]["sender"] | "---";

 currentCallId =
  doc["state"]["call_id"] | 0UL;

if (callActive) {
  showStatus("CALLING!", sender, currentCallId, "Server OK");
} else {
  showStatus("Waiting...", "---", currentCallId, "Server OK");
}

  Serial.println(payload);
}

//停止処理
void clearCall() {
  if (!callActive || currentCallId == 0) {
    return;
  }

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi unavailable. Clear not sent.");
    return;
  }

  WiFiClientSecure client;
  client.setInsecure();

  HTTPClient http;
  http.setTimeout(5000);

  const char* clearUrl =
    "https://bananagunso-dev.f5.si/emergency/api/clear.php";

  if (!http.begin(client, clearUrl)) {
    Serial.println("HTTP begin failed");
    return;
  }

  http.addHeader(
    "Authorization",
    String("Bearer ") + API_TOKEN
  );

  http.addHeader(
    "Content-Type",
    "application/json"
  );

  String payload =
    String("{\"call_id\":") +
    String(currentCallId) + "}";

  Serial.println("Sending clear request...");

  int httpCode = http.POST(payload);

  if (httpCode == HTTP_CODE_OK) {
    Serial.println(http.getString());
    Serial.println("CLEAR SENT OK");

    callActive = false;
     showStatus("Waiting...", "---",
             currentCallId, "Server OK");
  } else {
    Serial.print("Clear failed: ");
    Serial.println(httpCode);
  }

  http.end();
}

//停止ボタンの監視処理
void checkButton() {
  int reading = digitalRead(BUTTON_PIN);

  if (reading != lastButtonReading) {
    lastButtonChange = millis();
  }

  if (millis() - lastButtonChange >= DEBOUNCE_TIME) {
    if (reading != buttonState) {
      buttonState = reading;

      if (buttonState == LOW) {
        Serial.println("STOP BUTTON PRESSED");
        clearCall();
      }
    }
  }

  lastButtonReading = reading;
}


// --------------------------
// 初期化
// --------------------------
void setup() {
  Serial.begin(115200);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(BUTTON_PIN, INPUT_PULLUP);
  digitalWrite(BUZZER_PIN, LOW);
  oled.begin();
  showStatus("Starting", "---", 0, "Boot");

  connectWiFi();

  checkServer();
  lastPoll = millis();
}

void updateBuzzer(bool active) {
  static bool buzzerOn = false;
  static unsigned long lastChange = 0;

  unsigned long now = millis();

  if (!active) {
    buzzerOn = false;
    digitalWrite(BUZZER_PIN, LOW);
    lastChange = now;
    return;
  }

  unsigned long interval = buzzerOn ? 250 : 750;

  if (now - lastChange >= interval) {
    buzzerOn = !buzzerOn;
    digitalWrite(BUZZER_PIN, buzzerOn ? HIGH : LOW);
    lastChange = now;
  }
}

// --------------------------
// メインループ
// --------------------------
void loop() {
  checkButton();

  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
    lastPoll = millis();
  }

  if (millis() - lastPoll >= POLL_INTERVAL) {
    lastPoll = millis();
    checkServer();
  }

  updateBuzzer(callActive);

  delay(10);
}
