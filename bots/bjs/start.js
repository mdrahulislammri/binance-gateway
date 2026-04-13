// Command: /start
// Answer: (leave blank)

Bot.sendMessage(
  "Welcome to *Binance Pay Bot*!\n\n" +
  "/deposit — Add funds\n" +
  "/balance — Check your balance"
);

// Clear all pending states
User.setProperty("state", null, "string");
User.setProperty("state_amount", null, "float");
User.setProperty("admin_state", null, "string");
