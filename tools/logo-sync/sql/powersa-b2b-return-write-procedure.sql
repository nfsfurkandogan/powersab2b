/*
  Powersa B2B return write procedure for Logo Go Wings firm 003 period 01.

  It writes every approved B2B iade/hasarli/arizali request to:
  - LG_003_01_INVOICE (03 Toptan Satis Iade Faturasi, TRCODE = 3)
  - LG_003_01_STFICHE (linked stock fiche, TRCODE = 3)
  - LG_003_01_STLINE  (return lines, TRCODE = 3, IOCODE = 1)

  Hasarli/arizali requests are also exported by PowersaB2B_ExportReturnScrap
  as fire fisi. This procedure deliberately handles the shared sales return
  invoice side for all three request types.
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

    DECLARE @ExistingExternalRef NVARCHAR(128);
    EXEC dbo.PowersaB2B_BeginExport @ExportKey, N'return', @PayloadJson, @ExistingExternalRef OUTPUT;
    IF @ExistingExternalRef IS NOT NULL
    BEGIN
        SET @ExternalRef = @ExistingExternalRef;
        RETURN;
    END;

    DECLARE @CustomerRef INT = TRY_CONVERT(INT, NULLIF(LTRIM(RTRIM(@CustomerExternalRef)), N''));
    DECLARE @RequestId BIGINT = TRY_CONVERT(BIGINT, JSON_VALUE(@PayloadJson, '$.return_request_id'));
    DECLARE @FicheNo VARCHAR(17) = CONVERT(VARCHAR(17), RIGHT(REPLICATE('0', 17) + CONVERT(VARCHAR(32), COALESCE(@RequestId, ABS(CHECKSUM(@ExportKey)))), 17));
    DECLARE @Docode VARCHAR(33) = CONVERT(VARCHAR(33), LEFT(COALESCE(NULLIF(@RequestNo, N''), NULLIF(@CustomerCode, N''), @ExportKey), 33));
    DECLARE @Specode VARCHAR(11) = CONVERT(VARCHAR(11), LEFT(@ExportKey, 11));
    DECLARE @CyphCode VARCHAR(11) = CONVERT(VARCHAR(11), LEFT(COALESCE(NULLIF(@ReturnType, N''), N''), 11));
    DECLARE @Now DATETIME = GETDATE();
    DECLARE @Hour SMALLINT = DATEPART(HOUR, @Now);
    DECLARE @Minute SMALLINT = DATEPART(MINUTE, @Now);
    DECLARE @Second SMALLINT = DATEPART(SECOND, @Now);
    DECLARE @InvoiceRef INT;
    DECLARE @StockFicheRef INT;
    DECLARE @Total DECIMAL(18, 4);
    DECLARE @VatTotal DECIMAL(18, 4);
    DECLARE @NetTotal DECIMAL(18, 4);

    DECLARE @Lines TABLE (
        RowNo INT IDENTITY(1, 1) NOT NULL,
        StockRef INT NULL,
        ProductCode NVARCHAR(64) NULL,
        Quantity DECIMAL(18, 4) NOT NULL,
        Price DECIMAL(18, 4) NOT NULL,
        LineTotal DECIMAL(18, 4) NOT NULL,
        VatRate DECIMAL(18, 4) NOT NULL,
        VatAmount DECIMAL(18, 4) NOT NULL DEFAULT (0),
        UomRef INT NULL,
        UsRef INT NULL,
        LineExp NVARCHAR(251) NULL
    );

    IF @CustomerRef IS NULL
    BEGIN
        SELECT TOP 1 @CustomerRef = LOGICALREF
        FROM dbo.LG_003_CLCARD WITH (NOLOCK)
        WHERE CODE = CONVERT(VARCHAR(17), @CustomerCode)
          AND ISNULL(ACTIVE, 0) = 0;
    END;

    INSERT INTO @Lines (
        StockRef, ProductCode, Quantity, Price, LineTotal, VatRate, UomRef, UsRef, LineExp
    )
    SELECT
        TRY_CONVERT(INT, COALESCE(NULLIF(logo_stock_ref, N''), NULLIF(product_external_ref, N''))),
        product_code,
        CASE WHEN TRY_CONVERT(DECIMAL(18, 4), quantity) > 0 THEN TRY_CONVERT(DECIMAL(18, 4), quantity) ELSE 1 END,
        COALESCE(TRY_CONVERT(DECIMAL(18, 4), unit_price), 0),
        COALESCE(TRY_CONVERT(DECIMAL(18, 4), line_total), 0),
        COALESCE(TRY_CONVERT(DECIMAL(18, 4), vat_rate), 0),
        TRY_CONVERT(INT, NULLIF(uom_ref, N'')),
        TRY_CONVERT(INT, NULLIF(unitset_ref, N'')),
        CONVERT(NVARCHAR(251), LEFT(COALESCE(NULLIF(reason_note, N''), NULLIF(resolution_note, N''), NULLIF(@ReasonCode, N''), NULLIF(@ReturnType, N''), N'Powersa B2B satis iade'), 251))
    FROM OPENJSON(@PayloadJson, '$.items')
    WITH (
        product_external_ref NVARCHAR(128) '$.product_external_ref',
        product_code NVARCHAR(64) '$.product_code',
        quantity NVARCHAR(32) '$.quantity',
        unit_price NVARCHAR(32) '$.unit_price',
        line_total NVARCHAR(32) '$.line_total',
        vat_rate NVARCHAR(32) '$.vat_rate',
        logo_stock_ref NVARCHAR(128) '$.logo.stock_ref',
        unitset_ref NVARCHAR(128) '$.logo.unitset_ref',
        uom_ref NVARCHAR(128) '$.logo.uom_ref',
        reason_note NVARCHAR(251) '$.reason_note',
        resolution_note NVARCHAR(251) '$.resolution_note'
    );

    IF NOT EXISTS (SELECT 1 FROM @Lines)
    BEGIN
        INSERT INTO @Lines (
            StockRef, ProductCode, Quantity, Price, LineTotal, VatRate, UomRef, UsRef, LineExp
        )
        VALUES (
            TRY_CONVERT(INT, COALESCE(JSON_VALUE(@PayloadJson, '$.logo.stock_ref'), JSON_VALUE(@PayloadJson, '$.product_external_ref'))),
            JSON_VALUE(@PayloadJson, '$.product_code'),
            CASE WHEN TRY_CONVERT(DECIMAL(18, 4), JSON_VALUE(@PayloadJson, '$.quantity')) > 0 THEN TRY_CONVERT(DECIMAL(18, 4), JSON_VALUE(@PayloadJson, '$.quantity')) ELSE 1 END,
            COALESCE(TRY_CONVERT(DECIMAL(18, 4), JSON_VALUE(@PayloadJson, '$.unit_price')), 0),
            COALESCE(TRY_CONVERT(DECIMAL(18, 4), JSON_VALUE(@PayloadJson, '$.line_total')), @Amount),
            COALESCE(TRY_CONVERT(DECIMAL(18, 4), JSON_VALUE(@PayloadJson, '$.vat_rate')), 0),
            TRY_CONVERT(INT, JSON_VALUE(@PayloadJson, '$.logo.uom_ref')),
            TRY_CONVERT(INT, JSON_VALUE(@PayloadJson, '$.logo.unitset_ref')),
            CONVERT(NVARCHAR(251), LEFT(COALESCE(NULLIF(@ReasonCode, N''), NULLIF(@ReturnType, N''), N'Powersa B2B satis iade'), 251))
        );
    END;

    UPDATE lines
       SET StockRef = items.LOGICALREF
      FROM @Lines AS lines
      INNER JOIN dbo.LG_003_ITEMS AS items WITH (NOLOCK)
        ON items.CODE = CONVERT(VARCHAR(25), lines.ProductCode)
     WHERE lines.StockRef IS NULL
       AND NULLIF(lines.ProductCode, N'') IS NOT NULL;

    UPDATE lines
       SET UsRef = COALESCE(lines.UsRef, items.UNITSETREF),
           UomRef = COALESCE(lines.UomRef, unitLines.LOGICALREF)
      FROM @Lines AS lines
      INNER JOIN dbo.LG_003_ITEMS AS items WITH (NOLOCK)
        ON items.LOGICALREF = lines.StockRef
      LEFT JOIN dbo.LG_003_UNITSETL AS unitLines WITH (NOLOCK)
        ON unitLines.UNITSETREF = items.UNITSETREF
       AND ISNULL(unitLines.MAINUNIT, 0) = 1;

    IF EXISTS (SELECT 1 FROM @Lines WHERE StockRef IS NULL)
        THROW 51030, 'Logo stock item could not be resolved for return export.', 1;

    UPDATE @Lines
       SET LineTotal = CASE WHEN LineTotal > 0 THEN LineTotal ELSE Quantity * Price END;

    UPDATE @Lines
       SET VatAmount = LineTotal * VatRate / 100;

    SELECT
        @Total = COALESCE(SUM(LineTotal), 0),
        @VatTotal = COALESCE(SUM(VatAmount), 0)
    FROM @Lines;

    SET @NetTotal = @Total + @VatTotal;

    BEGIN TRANSACTION;

    INSERT INTO dbo.LG_003_01_INVOICE (
        GRPCODE, TRCODE, FICHENO, DATE_, DOCODE, SPECODE, CYPHCODE, CLIENTREF,
        SOURCEINDEX, SOURCECOSTGRP, CANCELLED, ACCOUNTED, VAT, TOTALDISCOUNTS,
        TOTALDISCOUNTED, TOTALVAT, GROSSTOTAL, NETTOTAL, GENEXP1, GENEXP2, GENEXP3, GENEXP4,
        TRCURR, TRRATE, REPORTRATE, REPORTNET, PAYDEFREF, BRANCH, DEPARTMENT,
        CAPIBLOCK_CREATEDBY, CAPIBLOCK_CREADEDDATE, CAPIBLOCK_CREATEDHOUR,
        CAPIBLOCK_CREATEDMIN, CAPIBLOCK_CREATEDSEC
    )
    VALUES (
        2, 3, @FicheNo, @ReturnDate, @Docode, @Specode, @CyphCode, COALESCE(@CustomerRef, 0),
        0, 0, 0, 0, CONVERT(FLOAT, @VatTotal), 0,
        CONVERT(FLOAT, @Total), CONVERT(FLOAT, @VatTotal), CONVERT(FLOAT, @Total), CONVERT(FLOAT, @NetTotal),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@RequestNo, N''), @ExportKey), 51)),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@ReturnType, N''), N''), 51)),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@ReasonCode, N''), N''), 51)),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@CustomerCode, N''), N''), 51)),
        0, 1, 1, CONVERT(FLOAT, @NetTotal), 0, 0, 0,
        1, @Now, @Hour, @Minute, @Second
    );

    SET @InvoiceRef = SCOPE_IDENTITY();

    INSERT INTO dbo.LG_003_01_STFICHE (
        GRPCODE, TRCODE, IOCODE, FICHENO, DATE_, FTIME, DOCODE, SPECODE, CYPHCODE,
        CLIENTREF, SOURCETYPE, SOURCEINDEX, SOURCECOSTGRP, BRANCH, DEPARTMENT,
        CANCELLED, BILLED, ACCOUNTED, UPDCURR, INUSE, ADDDISCOUNTS,
        TOTALDISCOUNTS, TOTALDISCOUNTED, ADDEXPENSES, TOTALEXPENSES,
        GROSSTOTAL, NETTOTAL, REPORTRATE, REPORTNET, GENEXP1, GENEXP2, GENEXP3, GENEXP4,
        CAPIBLOCK_CREATEDBY, CAPIBLOCK_CREADEDDATE, CAPIBLOCK_CREATEDHOUR,
        CAPIBLOCK_CREATEDMIN, CAPIBLOCK_CREATEDSEC
    )
    VALUES (
        2, 3, 1, @FicheNo, @ReturnDate, 0, @Docode, @Specode, @CyphCode,
        COALESCE(@CustomerRef, 0), 0, 0, 0, 0, 0,
        0, 1, 0, 0, 0, 0,
        0, CONVERT(FLOAT, @Total), 0, 0,
        CONVERT(FLOAT, @Total), CONVERT(FLOAT, @NetTotal), 1, CONVERT(FLOAT, @NetTotal),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@RequestNo, N''), @ExportKey), 51)),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@ReturnType, N''), N''), 51)),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@ReasonCode, N''), N''), 51)),
        CONVERT(VARCHAR(51), LEFT(COALESCE(NULLIF(@CustomerCode, N''), N''), 51)),
        1, @Now, @Hour, @Minute, @Second
    );

    SET @StockFicheRef = SCOPE_IDENTITY();

    INSERT INTO dbo.LG_003_01_STLINE (
        STOCKREF, LINETYPE, TRCODE, DATE_, FTIME, GLOBTRANS, CALCTYPE,
        SOURCETYPE, SOURCEINDEX, SOURCECOSTGRP, DESTTYPE, DESTINDEX, DESTCOSTGRP,
        FACTORYNR, IOCODE, STFICHEREF, STFICHELNNO, INVOICEREF, INVOICELNNO,
        CLIENTREF, PAYDEFREF, SPECODE, AMOUNT, PRICE, TOTAL, PRCURR, PRPRICE,
        TRCURR, TRRATE, REPORTRATE, LINEEXP, UOMREF, USREF, UINFO1, UINFO2,
        VATINC, VAT, VATAMNT, VATMATRAH, BILLEDITEM, BILLED, CANCELLED,
        LINENET, MONTH_, YEAR_
    )
    SELECT
        src.StockRef, 0, 3, @ReturnDate, 0, 0, 0,
        0, 0, 0, 0, 0, 0,
        0, 1, @StockFicheRef, src.RowNo, @InvoiceRef, src.RowNo,
        COALESCE(@CustomerRef, 0), 0, @Specode, CONVERT(FLOAT, src.Quantity),
        CONVERT(FLOAT, src.Price), CONVERT(FLOAT, src.LineTotal), 0, CONVERT(FLOAT, src.Price),
        0, 1, 1, CONVERT(VARCHAR(251), src.LineExp), COALESCE(src.UomRef, 0), COALESCE(src.UsRef, 0), 1, 1,
        0, CONVERT(FLOAT, src.VatRate), CONVERT(FLOAT, src.VatAmount), CONVERT(FLOAT, src.LineTotal),
        0, 1, 0, CONVERT(FLOAT, src.LineTotal), MONTH(@ReturnDate), YEAR(@ReturnDate)
    FROM @Lines AS src
    ORDER BY src.RowNo;

    SET @ExternalRef = CONCAT(N'INVOICE-', @InvoiceRef);
    EXEC dbo.PowersaB2B_FinishExport @ExportKey, @ExternalRef;

    COMMIT TRANSACTION;
END;
GO
