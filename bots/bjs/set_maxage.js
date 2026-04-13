// Command: /set_maxage
// need_reply: true
// Answer: (leave blank)

var adminId = Bot.getProperty("ADMIN_ID");
if (!adminId || String(user.telegramid) !== String(adminId)) {
    Bot.sendMessage("Access denied.");
    return;
}

Bot.sendMessage("Enter max transaction age in hours (e.g. 24):");
User.setProperty("admin_state", "SET_MAXAGE", "string");
