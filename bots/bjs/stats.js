// Command: /stats
// Answer: (leave blank)

var adminId = Bot.getProperty("ADMIN_ID");
if (!adminId || String(user.telegramid) !== String(adminId)) {
    Bot.sendMessage("Access denied.");
    return;
}

var totalDeposits = parseFloat(Bot.getProperty("total_deposits") || 0);
var totalUsers    = parseInt(Bot.getProperty("total_users") || 0);
var coin          = Bot.getProperty("COIN") || "USDT";

Bot.sendMessage(
    "*Bot Statistics*\n\n" +
    "Total Users: *" + totalUsers + "*\n" +
    "Total Deposits: *" + totalDeposits.toFixed(2) + " " + coin + "*"
);
