// Command: /set_coin
// need_reply: true
// Answer: (leave blank)

var adminId = Bot.getProperty("ADMIN_ID");
if (!adminId || String(user.telegramid) !== String(adminId)) {
    Bot.sendMessage("Access denied.");
    return;
}

Bot.sendMessage("Enter accepted coin (e.g. USDT):");
User.setProperty("admin_state", "SET_COIN", "string");
