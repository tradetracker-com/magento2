# CLI Commands

Command line tools for the TradeTracker Connect extension, for manual feed generation, automation scripts and troubleshooting.

---

## Available Commands

| Command | Description |
|---------|-------------|
| `tradetracker:feed:create` | Generate the product feed |

---

## tradetracker:feed:create

Generates the TradeTracker product feed for every store view that has the feed enabled. This is the same generation that runs via cron.

```bash
# Generate the feed for all enabled store views
bin/magento tradetracker:feed:create

# Generate the feed for one store view only
bin/magento tradetracker:feed:create --store-id=1
```

**Options:**

| Option | Description |
|--------|-------------|
| `--store-id=<id>` | Generate the feed for this store view only |
