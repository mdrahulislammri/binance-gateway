// Command: /set_uid
// need_reply: true
// Answer: (leave blank)

var adminId = Bot.getProperty("ADMIN_ID");
if (!adminId || String(user.telegramid) !== String(adminId)) {
    Bot.sendMessage("Access denied.");
    return;
}

Bot.sendMessage("Enter your Binance UID:");
User.setProperty("admin_state", "SET_UID", "string");
