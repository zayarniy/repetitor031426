<?php
// footer.php
?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Подвал с цитатой -->
    <footer class="footer mt-auto py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">
                <i class="bi bi-quote"></i> 
                Нет такого знания, что не являлось бы силой 
                <i class="bi bi-quote"></i>
            </span>
            <br>
            <small class="text-muted">
                &copy; <?php echo date('Y'); ?> Дневник репетитора
            </small>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
    .footer {
        position: relative;
        bottom: 0;
        width: 100%;
        border-top: 1px solid #dee2e6;
        margin-left: -24px;
        padding-left: 24px;
        padding-right: 24px;
    }
    .footer .bi-quote {
        color: #6c757d;
        font-size: 0.9rem;
    }
    </style>
</body>
</html>