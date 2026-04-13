// Command: /admin
// Answer: (leave blank)
// Description: Admin panel — bot owner only

var adminId = Bot.getProperty("ADMIN_ID");

if (!adminId || String(user.telegramid) !== String(adminId)) {
    Bot.sendMessage("Access denied.");
    return;
}

Bot.sendMessage(
    "*Admin Panel*\n\n" +
    "Current Settings:\n" +
    "• UID: `" + (Bot.getProperty("BINANCE_UID") || "not set") + "`\n" +
    "• API Key: `" + (Bot.getProperty("BINANCE_API_KEY") ? "****set****" : "not set") + "`\n" +
    "• Coin: `" + (Bot.getProperty("COIN") || "USDT") + "`\n" +
    "• Max Age: `" + (Bot.getProperty("MAX_AGE_HOURS") || "24") + "h`\n\n" +
    "Commands:\n" +
    "/set\\_uid — Set Binance UID\n" +
    "/set\\_apikey — Set API Key\n" +
    "/set\\_apisecret — Set API Secret\n" +
    "/set\\_coin — Set accepted coin\n" +
    "/set\\_maxage — Set max transaction age (hours)\n" +
    "/stats — View bot statistics"
);
