TODO : 
- check constraint remaning can't be less than 0

- dispatchEvents() // LowStockDetected, InventoryTransactionRecorded, StockDecreased, StockIncreased etc;

- correct exceptions

- auto-creation of items and locations

- laisser la distribution des stocks dans le transfert a l'utilisateur via un link_id par exemple

- vendor_id missing

- peremption date and unit_cost are really important for IN movement intervenant surtout dans le debat de la validation de Pair unique

- when peremption is used, can i ajust the stock like i want ? risk of fake peremption alert

- adopt a convention for methods signature

- verify all columns are correctly save (especially metadata ; peremption_date)