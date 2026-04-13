/**
 * Binance Pay Proxy — Node.js
 * For BJS Bot — handles HMAC signature server-side
 * 
 * @author Md Rahul Islam <https://github.com/mdrahulislammri>
 * 
 * Setup:
 *   npm install express axios
 *   node binance_proxy.js
 */

const express = require('express');
const axios   = require('axios');
const crypto  = require('crypto');

const app = express();

const PORT       = process.env.PORT || 3000;
const AUTH_TOKEN = process.env.AUTH_TOKEN || 'YOUR_SECRET_TOKEN'; // Change this!

app.get('/binance_proxy', async (req, res) => {
    // Auth check
    if (req.query.token !== AUTH_TOKEN) {
        return res.json({ ok: false, error: 'Unauthorized' });
    }

    const apiKey    = req.query.api_key    || '';
    const apiSecret = req.query.api_secret || '';
    const limit     = Math.min(parseInt(req.query.limit || '100'), 100);

    if (!apiKey || !apiSecret) {
        return res.json({ ok: false, error: 'Missing credentials' });
    }

    // Build signed request
    const timestamp = Date.now();
    const query     = `limit=${limit}&timestamp=${timestamp}`;
    const signature = crypto.createHmac('sha256', apiSecret).update(query).digest('hex');
    const url       = `https://api.binance.com/sapi/v1/pay/transactions?${query}&signature=${signature}`;

    try {
        const response = await axios.get(url, {
            headers: { 'X-MBX-APIKEY': apiKey },
            timeout: 30000,
        });

        const data = response.data;

        if (data.code !== '000000') {
            return res.json({
                ok:    false,
                error: data.message || data.errorMessage || 'API error',
                code:  data.code,
            });
        }

        return res.json({ ok: true, data: data.data || [] });

    } catch (err) {
        return res.json({ ok: false, error: 'Connection error: ' + err.message });
    }
});

app.listen(PORT, () => {
    console.log(`Binance Proxy running on port ${PORT}`);
});
