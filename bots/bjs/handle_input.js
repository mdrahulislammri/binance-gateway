// Command: * (catches all text input)
// need_reply: false
// Answer: (leave blank)

// ── Pure JS HMAC-SHA256 (no library needed) ───────────────────────────────────
function hmacSHA256(msg, key) {
    function sha256(data) {
        function rr(v,a){return(v>>>a)|(v<<(32-a));}
        var mp=Math.pow,mw=mp(2,32),i,j,r='',w=[],abl=data.length*8,h=sha256.h||[],k=sha256.k||[],pc=k.length,ic={};
        sha256.h=h;sha256.k=k;
        for(var cd=2;pc<64;cd++){if(!ic[cd]){for(i=0;i<313;i+=cd)ic[i]=cd;h[pc]=(mp(cd,.5)*mw)|0;k[pc++]=(mp(cd,1/3)*mw)|0;}}
        data+='\x80';
        while(data.length%64-56)data+='\x00';
        for(i=0;i<data.length;i++){j=data.charCodeAt(i);if(j>>8)return;w[i>>2]|=j<<((3-i)%4)*8;}
        w[w.length]=((abl/mw)|0);w[w.length]=(abl);
        for(j=0;j<w.length;){var ww=w.slice(j,j+=16),oh=h.slice(0);h=sha256.h;
        for(i=0;i<64;i++){var i2=i+j-16,w15=ww[i-15],w2=ww[i-2],a=h[0],e=h[4];
        var t1=h[7]+(rr(e,6)^rr(e,11)^rr(e,25))+((e&h[5])^(~e&h[6]))+k[i]+(ww[i]=(i<16)?ww[i]:(ww[i-16]+(rr(w15,7)^rr(w15,18)^(w15>>>3))+ww[i-7]+(rr(w2,17)^rr(w2,19)^(w2>>>10)))|0);
        var t2=(rr(a,2)^rr(a,13)^rr(a,22))+((a&h[1])^(a&h[2])^(h[1]&h[2]));
        h=[(t1+t2)|0].concat(h);h[4]=(h[4]+t1)|0;h.length=8;}
        h=h.map(function(v,i){return(v+oh[i])|0;});}
        for(i=0;i<8;i++)for(j=3;j+1;j--){var b=(h[i]>>(j*8))&255;r+=((b<16)?0:'')+b.toString(16);}
        return r;
    }
    function h2s(hex){var r='';for(var i=0;i<hex.length;i+=2)r+=String.fromCharCode(parseInt(hex.substr(i,2),16));return r;}
    var bs=64;if(key.length>bs)key=h2s(sha256(key));
    while(key.length<bs)key+='\x00';
    var op='',ip='';
    for(var i=0;i<bs;i++){var kb=key.charCodeAt(i);op+=String.fromCharCode(kb^0x5c);ip+=String.fromCharCode(kb^0x36);}
    return sha256(op+h2s(sha256(ip+msg)));
}
// ─────────────────────────────────────────────────────────────────────────────

var state      = User.getProperty("state");
var adminState = User.getProperty("admin_state");
var adminId    = Bot.getProperty("ADMIN_ID");
var isAdmin    = adminId && String(user.telegramid) === String(adminId);
var coin       = Bot.getProperty("COIN") || "USDT";
var uid        = Bot.getProperty("BINANCE_UID");
var apiKey     = Bot.getProperty("BINANCE_API_KEY");
var apiSec     = Bot.getProperty("BINANCE_API_SECRET");
var maxAge     = parseInt(Bot.getProperty("MAX_AGE_HOURS") || "24");

// ── Admin state handling ──────────────────────────────────────────────────────
if (isAdmin && adminState) {
    var val = message.trim();
    if (adminState === "SET_UID") {
        Bot.setProperty("BINANCE_UID", val, "string");
        Bot.sendMessage("Binance UID updated: `" + val + "`");
    } else if (adminState === "SET_APIKEY") {
        Bot.setProperty("BINANCE_API_KEY", val, "string");
        Bot.sendMessage("API Key updated.");
    } else if (adminState === "SET_APISECRET") {
        Bot.setProperty("BINANCE_API_SECRET", val, "string");
        Bot.sendMessage("API Secret updated.");
    } else if (adminState === "SET_COIN") {
        Bot.setProperty("COIN", val.toUpperCase(), "string");
        Bot.sendMessage("Coin updated: `" + val.toUpperCase() + "`");
    } else if (adminState === "SET_MAXAGE") {
        var hours = parseInt(val);
        if (isNaN(hours) || hours <= 0) { Bot.sendMessage("Invalid value. Enter a number (e.g. 24):"); return; }
        Bot.setProperty("MAX_AGE_HOURS", String(hours), "string");
        Bot.sendMessage("Max age updated: `" + hours + "h`");
    }
    User.setProperty("admin_state", null, "string");
    return;
}

// ── State: ENTER_AMOUNT ───────────────────────────────────────────────────────
if (state === "ENTER_AMOUNT") {
    var depositAmount = parseFloat(message);
    if (isNaN(depositAmount) || depositAmount <= 0) {
        Bot.sendMessage("Invalid amount. Please enter a valid number (e.g. `10.00`):");
        return;
    }
    User.setProperty("state", "ENTER_ORDER_ID", "string");
    User.setProperty("state_amount", depositAmount, "float");
    Bot.sendMessage(
        "Send exactly *" + depositAmount.toFixed(2) + " " + coin + "* to this Binance UID:\n\n" +
        "`" + uid + "`\n\n" +
        "Binance App → *Pay* → *Send* → enter UID → send *" + depositAmount.toFixed(2) + " " + coin + "*\n\n" +
        "After sending, paste your *Order ID* below:"
    );
    return;
}

// ── State: ENTER_ORDER_ID ─────────────────────────────────────────────────────
if (state === "ENTER_ORDER_ID") {
    var orderId       = message.trim();
    var pendingAmount = parseFloat(User.getProperty("state_amount") || 0);

    // Numeric check
    var isValidId = orderId.length >= 15;
    for (var c = 0; c < orderId.length && isValidId; c++) {
        if (orderId[c] < '0' || orderId[c] > '9') isValidId = false;
    }
    if (!isValidId) {
        Bot.sendMessage("Invalid Order ID format. Please check and try again:");
        return;
    }

    // Duplicate check
    if (Bot.getProperty("used_" + orderId)) {
        User.setProperty("state", null, "string");
        Bot.sendMessage("This Order ID has already been used.");
        return;
    }

    Bot.sendMessage("Verifying payment, please wait...");

    // Clear state BEFORE HTTP call to prevent re-trigger
    User.setProperty("state", null, "string");

    var proxyUrl = Bot.getProperty("PROXY_URL"); // e.g. https://yourdomain.com/binance_proxy.php
    var token    = Bot.getProperty("PROXY_TOKEN");

    HTTP.get({
        url: proxyUrl + "?token=" + token + "&api_key=" + apiKey + "&api_secret=" + apiSec,
        success: "/binance_verify_result",
        error:   "/binance_verify_error",
        params: {
            order_id: orderId,
            amount:   pendingAmount,
            coin:     coin,
            max_age:  maxAge
        }
    });
    return;
}

// ── No active state ───────────────────────────────────────────────────────────
Bot.sendMessage("Use /deposit to add funds or /balance to check your balance.");
