// Command: /deposit
// need_reply: true
// Answer: (leave blank)

var coin = Bot.getProperty("COIN") || "USDT";

Bot.sendMessage(
  "Enter the amount you want to deposit (" + coin + "):\n\nExample: `10.00`"
);

// Set state — waiting for amount
User.setProperty("state", "ENTER_AMOUNT", "string");
