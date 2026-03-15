TODO : 
- check constraint remaning can't be less than 0

- missing metadata field for stock
- ajouter stock_id à movement permet de completer un stock plutôt que d'en créer un nouveau pour un mouvement IN. Ne pas dépasser quantity inital + withTrashed autorisée

- dispatchEvents() // LowStockDetected, InventoryTransactionRecorded, StockDecreased, StockIncreased etc;

- choose the stocks (StockSelectionStrategy)

- correct exceptions

- auto-creation of items and locations