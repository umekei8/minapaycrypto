package com.minapay.pos.data.db

import androidx.room.*
import kotlinx.coroutines.flow.Flow
import java.math.BigDecimal

@Entity(tableName = "payments")
data class PaymentEntity(
    @PrimaryKey val paymentId: String,
    val merchantId: String,
    val terminalId: String,
    val fiatAmount: Int,
    val cryptoCurrency: String,
    val cryptoAmount: String,       // BigDecimal as String
    val rateJpy: String,
    val adjustedRateJpy: String,
    val rateMarkupPercent: Double,
    val feePercent: Double,
    val merchantAmountJpy: Int,
    val operatorFeeJpy: Int,
    val merchantWallet: String,
    val operatorWallet: String,
    val xrpDestinationTag: String,
    val qrPayload: String,
    val status: String,
    val txHash: String? = null,
    val confirmations: Int = 0,
    val isDemoConfirmed: Boolean = false,
    val expiresAt: Long,
    val detectedAt: Long? = null,
    val confirmedAt: Long? = null,
    val cancelledAt: Long? = null,
    val cancelMemo: String? = null,
    val createdAt: Long = System.currentTimeMillis()
)

@Dao
interface PaymentDao {
    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(payment: PaymentEntity)

    @Update
    suspend fun update(payment: PaymentEntity)

    @Query("SELECT * FROM payments WHERE paymentId = :id")
    suspend fun getById(id: String): PaymentEntity?

    @Query("SELECT * FROM payments WHERE terminalId = :terminalId ORDER BY createdAt DESC LIMIT 100")
    fun getAllFlow(terminalId: String): Flow<List<PaymentEntity>>

    @Query("SELECT * FROM payments WHERE terminalId = :terminalId ORDER BY createdAt DESC LIMIT 100")
    suspend fun getAll(terminalId: String): List<PaymentEntity>

    @Query("UPDATE payments SET status = :status, txHash = :txHash, confirmations = :confirmations, confirmedAt = :confirmedAt, isDemoConfirmed = :isDemo WHERE paymentId = :id")
    suspend fun confirm(id: String, status: String, txHash: String?, confirmations: Int, confirmedAt: Long, isDemo: Boolean)

    @Query("UPDATE payments SET status = :status, txHash = :txHash, confirmations = :confirmations, detectedAt = :detectedAt WHERE paymentId = :id")
    suspend fun detect(id: String, status: String, txHash: String?, confirmations: Int, detectedAt: Long)

    // 過去にCONFIRMEDまたはDETECTEDとして使われたTXハッシュを取得（前回TXの再利用防止）
    @Query("SELECT txHash FROM payments WHERE txHash IS NOT NULL AND status IN ('CONFIRMED','DETECTED') ORDER BY createdAt DESC LIMIT 50")
    suspend fun getUsedTxHashes(): List<String>

    @Query("UPDATE payments SET status = 'CANCELLED', cancelledAt = :at, cancelMemo = :memo WHERE paymentId = :id")
    suspend fun cancel(id: String, at: Long, memo: String?)

    @Query("UPDATE payments SET status = 'EXPIRED' WHERE status IN ('WAITING_PAYMENT','DETECTED') AND expiresAt < :now")
    suspend fun expireOld(now: Long)
}

@Database(entities = [PaymentEntity::class], version = 2, exportSchema = false)
abstract class MinaPayDatabase : RoomDatabase() {
    abstract fun paymentDao(): PaymentDao

    companion object {
        @Volatile private var INSTANCE: MinaPayDatabase? = null
        fun getInstance(context: android.content.Context): MinaPayDatabase =
            INSTANCE ?: synchronized(this) {
                INSTANCE ?: Room.databaseBuilder(
                    context.applicationContext,
                    MinaPayDatabase::class.java,
                    "minapay_db"
                ).build().also { INSTANCE = it }
            }
    }
}
