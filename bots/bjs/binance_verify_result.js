// Command: /binance_verify_result

var coin    = Bot.getProperty("COIN") || "USDT";
var p       = options || params || {};
var orderId = String(p.order_id || "");
var amount  = parseFloat(p.amount || 0);
var maxAge  = parseInt(p.max_age || 24);

// Parse proxy response
var data;
try {
    data = (typeof content === "string") ? JSON.parse(content) : content;
} catch(e) {
    Bot.sendMessage("Failed to parse response.");
    return;
}

if (!data.ok) {
    Bot.sendMessage("Error: " + (data.error || "Unknown error."));
    return;
}

var transactions = data.data || [];
var found = null;

for (var i = 0; i < transactions.length; i++) {
    var tx = transactions[i];
    if (String(tx.orderId) !== orderId) continue;

    var txAmount = parseFloat(tx.amount || 0);
    if (txAmount <= 0) {
        Bot.sendMessage("This is an outgoing payment, not an incoming one.");
        return;
    }
    if ((tx.currency || "").toUpperCase() !== coin.toUpperCase()) {
        Bot.sendMessage("Coin mismatch. Expected " + coin + " but got " + tx.currency);
        return;
    }
    if (Math.abs(txAmount - amount) > 0.01) {
        Bot.sendMessage("Amount mismatch. Expected " + amount.toFixed(2) + " but got " + txAmount.toFixed(2) + " " + coin);
        return;
    }
    var txTime = parseInt(tx.transactionTime || 0) / 1000;
    if (txTime > 0 && (Date.now() / 1000 - txTime) > (maxAge * 3600)) {
        Bot.sendMessage("Transaction is older than " + maxAge + " hours.");
        return;
    }
    found = tx;
    break;
}

if (!found) {
    Bot.sendMessage("Order ID not found in Binance Pay history.");
    return;
}

// Mark as used
Bot.setProperty("used_" + orderId, true, "boolean");

// Add balance
var currentBalance = parseFloat(User.getProperty("balance") || 0);
var newBalance     = Math.round((currentBalance + amount) * 100) / 100;
User.setProperty("balance", newBalance, "float");

// Update stats
var totalDeposits = parseFloat(Bot.getProperty("total_deposits") || 0);
Bot.setProperty("total_deposits", String(Math.round((totalDeposits + amount) * 100) / 100), "string");
if (!User.getProperty("counted")) {
    var totalUsers = parseInt(Bot.getProperty("total_users") || 0);
    Bot.setProperty("total_users", String(totalUsers + 1), "string");
    User.setProperty("counted", true, "boolean");
}
User.setProperty("state_amount", null, "float");

Bot.sendMessage(
    "Payment verified!\n\n" +
    "Amount added: *" + amount.toFixed(2) + " " + coin + "*\n" +
    "New balance: *" + newBalance.toFixed(2) + " " + coin + "*"
);
