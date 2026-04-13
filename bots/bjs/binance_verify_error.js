// Command: /binance_verify_error
// Called when HTTP request to Binance API fails

User.setProperty("state", null, "string");
Bot.sendMessage("Connection error. Please try again later.");
