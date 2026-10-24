// Cloudflare Email Worker for your domain's catch-all (OpenMeru Mail).
// 1. Always forwards a copy to your Gmail (a backup of every message).
// 2. Delivers the raw message to OpenMeru Mail, which files it in the
//    mailbox of the address it was sent to.
//
// Variables (Settings → Variables): GMAIL = you@gmail.com (a verified destination address),
// INBOUND_URL = https://yourdomain.com/hooks/inbound
// Secret: INBOUND_SECRET (same value as INBOUND_SECRET in the app's .env)

export default {
  async email(message, env, ctx) {
    const raw = await new Response(message.raw).arrayBuffer();

    const copy = message.forward(env.GMAIL).catch((err) => console.error("gmail forward failed", err));

    const url = env.INBOUND_URL + "?to=" + encodeURIComponent(message.to);
    let delivered = false;
    for (let attempt = 0; attempt < 3 && !delivered; attempt++) {
      try {
        const res = await fetch(url, {
          method: "POST",
          headers: { "Content-Type": "message/rfc822", "X-OpenMeru-Secret": env.INBOUND_SECRET },
          body: raw
        });
        delivered = res.ok;
        if (!res.ok) console.error("inbound", res.status, await res.text());
      } catch (err) {
        console.error("inbound fetch failed", err);
      }
    }

    await copy;
  }
};
