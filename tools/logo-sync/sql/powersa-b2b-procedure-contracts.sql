/*
  Powersa B2B -> Logo SQL Bridge procedure contracts

  Bu dosya bridge scriptlerinin bekledigi stored procedure imzalarini sabitler.
  Logo Objects kullanilmaz; bridge bu procedure'leri SQL Server uzerinde cagirir.

  ONEMLI:
  - Bu contract dosyasi Logo yazma blogunu bilerek bos birakir.
  - As-is kuruldugunda procedure'ler hata firlatir ve B2B kaydi "failed" olur.
  - Logo server'a erisildiginde "IMPLEMENT LOGO WRITE BLOCK" satirlari Logo'nun
    kendi numarator/fis/fatura/kasa kurallarina uygun yazma koduyla degistirilmelidir.
  - Basarili yazmada @ExternalRef Logo tarafindaki LOGICALREF/FICHENO gibi kalici
    referansla set edilmeli ve dbo.POWERSA_B2B_EXPORT_LOG kaydi guncellenmelidir.
*/

IF OBJECT_ID(N'dbo.POWERSA_B2B_EXPORT_LOG', N'U') IS NULL
BEGIN
    CREATE TABLE dbo.POWERSA_B2B_EXPORT_LOG (
        ID BIGINT IDENTITY(1, 1) NOT NULL CONSTRAINT PK_POWERSA_B2B_EXPORT_LOG PRIMARY KEY,
        EXPORT_KEY NVARCHAR(128) NOT NULL,
        DOCUMENT_TYPE NVARCHAR(64) NOT NULL,
        EXTERNAL_REF NVARCHAR(128) NULL,
        STATUS NVARCHAR(32) NOT NULL CONSTRAINT DF_POWERSA_B2B_EXPORT_LOG_STATUS DEFAULT (N'pending'),
        ERROR_MESSAGE NVARCHAR(2000) NULL,
        PAYLOAD_JSON NVARCHAR(MAX) NULL,
        CREATED_AT DATETIME2(0) NOT NULL CONSTRAINT DF_POWERSA_B2B_EXPORT_LOG_CREATED_AT DEFAULT (SYSUTCDATETIME()),
        UPDATED_AT DATETIME2(0) NOT NULL CONSTRAINT DF_POWERSA_B2B_EXPORT_LOG_UPDATED_AT DEFAULT (SYSUTCDATETIME())
    );

    CREATE UNIQUE INDEX UX_POWERSA_B2B_EXPORT_LOG_EXPORT_KEY
        ON dbo.POWERSA_B2B_EXPORT_LOG (EXPORT_KEY);
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_BeginExport
    @ExportKey NVARCHAR(128),
    @DocumentType NVARCHAR(64),
    @PayloadJson NVARCHAR(MAX),
    @ExistingExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;

    IF NULLIF(LTRIM(RTRIM(@ExportKey)), N'') IS NULL
        THROW 51000, 'ExportKey is required.', 1;

    IF @PayloadJson IS NOT NULL AND ISJSON(@PayloadJson) <> 1
        THROW 51000, 'PayloadJson must be valid JSON.', 1;

    SELECT @ExistingExternalRef = EXTERNAL_REF
    FROM dbo.POWERSA_B2B_EXPORT_LOG WITH (UPDLOCK, HOLDLOCK)
    WHERE EXPORT_KEY = @ExportKey
      AND STATUS = N'synced'
      AND EXTERNAL_REF IS NOT NULL;

    IF @ExistingExternalRef IS NOT NULL
        RETURN;

    MERGE dbo.POWERSA_B2B_EXPORT_LOG AS target
    USING (SELECT @ExportKey AS EXPORT_KEY) AS source
       ON target.EXPORT_KEY = source.EXPORT_KEY
    WHEN MATCHED THEN
        UPDATE SET
            DOCUMENT_TYPE = @DocumentType,
            STATUS = N'pending',
            ERROR_MESSAGE = NULL,
            PAYLOAD_JSON = @PayloadJson,
            UPDATED_AT = SYSUTCDATETIME()
    WHEN NOT MATCHED THEN
        INSERT (EXPORT_KEY, DOCUMENT_TYPE, STATUS, PAYLOAD_JSON)
        VALUES (@ExportKey, @DocumentType, N'pending', @PayloadJson);
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_FinishExport
    @ExportKey NVARCHAR(128),
    @ExternalRef NVARCHAR(128)
AS
BEGIN
    SET NOCOUNT ON;

    IF NULLIF(LTRIM(RTRIM(@ExternalRef)), N'') IS NULL
        THROW 51000, 'ExternalRef is required after Logo write.', 1;

    UPDATE dbo.POWERSA_B2B_EXPORT_LOG
       SET STATUS = N'synced',
           EXTERNAL_REF = @ExternalRef,
           ERROR_MESSAGE = NULL,
           UPDATED_AT = SYSUTCDATETIME()
     WHERE EXPORT_KEY = @ExportKey;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_FailUnimplementedWrite
    @ProcedureName SYSNAME
AS
BEGIN
    DECLARE @Message NVARCHAR(2048) =
        CONCAT(@ProcedureName, N' Logo write block is not implemented on this Logo SQL server.');

    THROW 51001, @Message, 1;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportCustomer
    @CustomerCode NVARCHAR(64),
    @Name NVARCHAR(255),
    @ContactName NVARCHAR(255) = NULL,
    @Email NVARCHAR(255) = NULL,
    @Phone NVARCHAR(32) = NULL,
    @City NVARCHAR(120) = NULL,
    @District NVARCHAR(120) = NULL,
    @TaxOffice NVARCHAR(255) = NULL,
    @TaxNumber NVARCHAR(32) = NULL,
    @CreditLimit DECIMAL(15, 2) = 0,
    @IsActive BIT = 1,
    @Address NVARCHAR(MAX) = NULL,
    @Iban NVARCHAR(64) = NULL,
    @ExportKey NVARCHAR(128),
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'customer', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Upsert LG_XXX_CLCARD and set @ExternalRef to LOGICALREF or CODE.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportCustomer';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportCollection
    @CustomerExternalRef NVARCHAR(128) = NULL,
    @CustomerCode NVARCHAR(64) = NULL,
    @CollectionDate DATE,
    @Method NVARCHAR(32) = NULL,
    @Amount DECIMAL(15, 2),
    @Currency NVARCHAR(3) = N'TRY',
    @ReferenceNo NVARCHAR(120) = NULL,
    @Note NVARCHAR(MAX) = NULL,
    @ExportKey NVARCHAR(128),
    @CashboxId INT = NULL,
    @CashboxCode NVARCHAR(64) = NULL,
    @CashboxName NVARCHAR(128) = NULL,
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'collection', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Write KSLINES/CLFLINE/PAYTRANS as required and set @ExternalRef.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportCollection';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportPosSale
    @CustomerExternalRef NVARCHAR(128) = NULL,
    @CustomerCode NVARCHAR(64) = NULL,
    @SaleDate DATE,
    @ReceiptNo NVARCHAR(64) = NULL,
    @SaleType NVARCHAR(32) = NULL,
    @DocumentType NVARCHAR(32) = NULL,
    @Subtotal DECIMAL(15, 2) = 0,
    @DiscountTotal DECIMAL(15, 2) = 0,
    @VatTotal DECIMAL(15, 2) = 0,
    @GrandTotal DECIMAL(15, 2) = 0,
    @CashboxCode NVARCHAR(64) = NULL,
    @ExportKey NVARCHAR(128),
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'pos-sale', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Write INVOICE/STFICHE/STLINE/PAYTRANS/KSLINES and set @ExternalRef.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportPosSale';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportPosExpense
    @ExpenseDate DATE,
    @Category NVARCHAR(80) = NULL,
    @Amount DECIMAL(15, 2),
    @Currency NVARCHAR(3) = N'TRY',
    @Note NVARCHAR(MAX) = NULL,
    @CashboxCode NVARCHAR(64) = NULL,
    @ExportKey NVARCHAR(128),
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'pos-expense', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Write approved expense/cash/accounting lines and set @ExternalRef.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportPosExpense';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportOrder
    @CustomerExternalRef NVARCHAR(128) = NULL,
    @CustomerCode NVARCHAR(64) = NULL,
    @OrderDate DATE,
    @OrderNo NVARCHAR(64) = NULL,
    @Currency NVARCHAR(3) = N'TRY',
    @Subtotal DECIMAL(15, 2) = 0,
    @DiscountTotal DECIMAL(15, 2) = 0,
    @VatTotal DECIMAL(15, 2) = 0,
    @GrandTotal DECIMAL(15, 2) = 0,
    @ExportKey NVARCHAR(128),
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'order', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Write ORFICHE/ORFLINE. Use $.items array from @PayloadJson.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportOrder';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportShipment
    @CustomerExternalRef NVARCHAR(128) = NULL,
    @CustomerCode NVARCHAR(64) = NULL,
    @ShipmentDate DATE,
    @ShipmentNo NVARCHAR(64) = NULL,
    @OrderNo NVARCHAR(64) = NULL,
    @WarehouseCode NVARCHAR(64) = NULL,
    @Subtotal DECIMAL(15, 2) = 0,
    @VatTotal DECIMAL(15, 2) = 0,
    @GrandTotal DECIMAL(15, 2) = 0,
    @ExportKey NVARCHAR(128),
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'shipment', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Write STFICHE/STLINE and invoice if required. Use $.items array.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportShipment';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportReturn
    @CustomerExternalRef NVARCHAR(128) = NULL,
    @CustomerCode NVARCHAR(64) = NULL,
    @ReturnDate DATE,
    @RequestNo NVARCHAR(64) = NULL,
    @ReturnType NVARCHAR(32) = NULL,
    @ReasonCode NVARCHAR(64) = NULL,
    @Amount DECIMAL(15, 2) = 0,
    @Currency NVARCHAR(3) = N'TRY',
    @ExportKey NVARCHAR(128),
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'return', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Write (03) Toptan Satis Iade Faturasi to INVOICE/STFICHE/STLINE
    -- with INVOICE.TRCODE = 3, STFICHE.TRCODE = 3, STLINE.TRCODE = 3.
    -- Hasarli/arizali returns still also flow through PowersaB2B_ExportReturnScrap.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportReturn';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO

CREATE OR ALTER PROCEDURE dbo.PowersaB2B_ExportReturnScrap
    @CustomerExternalRef NVARCHAR(128) = NULL,
    @CustomerCode NVARCHAR(64) = NULL,
    @ScrapDate DATE,
    @DocumentNo NVARCHAR(64) = NULL,
    @RequestNo NVARCHAR(64) = NULL,
    @ReturnType NVARCHAR(32) = NULL,
    @ReasonCode NVARCHAR(64) = NULL,
    @Amount DECIMAL(15, 2) = 0,
    @Currency NVARCHAR(3) = N'TRY',
    @ExportKey NVARCHAR(128),
    @PayloadJson NVARCHAR(MAX) = NULL,
    @ExternalRef NVARCHAR(128) OUTPUT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'return-scrap', @PayloadJson, @ExternalRef OUTPUT;
    IF @ExternalRef IS NOT NULL RETURN;

    -- IMPLEMENT LOGO WRITE BLOCK:
    -- Write STFICHE/STLINE with STFICHE.TRCODE = 11 (Fire fisi).
    -- Use @DocumentNo for STFICHE.DOCODE; B2B sends the customer code here.
    -- Use $.items array for product lines. @ReturnType is damaged/faulty.
    EXEC dbo.PowersaB2B_FailUnimplementedWrite N'dbo.PowersaB2B_ExportReturnScrap';

    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;
END;
GO
