// Command: /balance
// Answer: (leave blank)

var balance = User.getProperty("balance") || 0;
var coin    = Bot.getProperty("COIN") || "USDT";

Bot.sendMessage(
  "Your balance: *" + parseFloat(balance).toFixed(2) + " " + coin + "*"
);
