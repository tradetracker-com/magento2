# CLI Commands

Command line tools for the TradeTracker Connect extension, for manual feed generation, automation scripts and troubleshooting.

---

## Available Commands

| Command | Description |
|---------|-------------|
| `tradetracker:feed:create` | Generate the product feed |
| `tradetracker:transaction:process-queue` | Send the queued transaction assessments to TradeTracker |

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

---

## tradetracker:transaction:process-queue

Sends the assessments that a cancelled order or a credit memo queued to TradeTracker: a rejection with its reason, or an acceptance for the amount that still stands. The cron job `tradetracker_process_transaction_queue` runs the same thing every five minutes; the command is for running it by hand or watching what it does.

```bash
bin/magento tradetracker:transaction:process-queue
```

The output counts the rows it handled, for example `Processed 3: 2 done, 1 refused, 0 failed for good, 0 postponed.` A refused row is one TradeTracker answered with an error; it is tried again on the next run and given up on after five refusals. A postponed row is one TradeTracker did not answer at all within the API Timeout; it is not counted against the row, and the run stops there so the rows behind it do not each wait out the same silence.
