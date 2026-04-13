// Command: /set_apikey
// need_reply: true
// Answer: (leave blank)

var adminId = Bot.getProperty("ADMIN_ID");
if (!adminId || String(user.telegramid) !== String(adminId)) {
    Bot.sendMessage("Access denied.");
    return;
}

Bot.sendMessage("Enter your Binance API Key:");
User.setProperty("admin_state", "SET_APIKEY", "string");
