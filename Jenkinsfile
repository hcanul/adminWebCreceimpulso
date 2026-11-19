pipeline {
    agent { label 'bussix-linux' }
    options {
        disableConcurrentBuilds()
        timestamps()
        timeout(time: 45, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '15', artifactNumToKeepStr: '3'))
        skipDefaultCheckout(true)
    }
    parameters { booleanParam(name: 'DEPLOY_CPANEL', defaultValue: false, description: 'Forzar publicación manual a pruebas; main se publica automáticamente al pasar CI') }
    triggers { pollSCM('H/10 * * * *') }
    environment {
        CI = 'true'
        QCLI_API_URL = 'https://pruebas.creceimpulso.com/api'
        NODE_OPTIONS = '--max-old-space-size=1536'
        APP_ENV = 'testing'
        DB_CONNECTION = 'sqlite'
        DB_DATABASE = ':memory:'
        DB_URL = ''
        CACHE_STORE = 'array'
        SESSION_DRIVER = 'array'
        QUEUE_CONNECTION = 'sync'
        MAIL_MAILER = 'array'
    }
    stages {
        stage('Checkout') {
            steps {
                script {
                    def revision = checkout scm
                    env.CI_GIT_BRANCH = revision.GIT_BRANCH ?: ''
                    if (!env.CI_GIT_BRANCH) {
                        error('Git no devolvió la rama de la revisión; se detiene para evitar omitir el despliegue silenciosamente.')
                    }
                    echo "Rama comprobada: ${env.CI_GIT_BRANCH}"
                }
            }
        }
        stage('Herramientas') {
            steps {
                sh 'bash ci/setup-tools'
                sh 'php --version && composer --version'
                script {
                    env.APP_KEY = sh(returnStdout: true, script: 'php -r \'echo "base64:".base64_encode(random_bytes(32));\'').trim()
                }
            }
        }
        stage('Laravel: dependencias y pruebas') {
            steps {
                dir('backend') {
                    sh 'composer validate --no-check-publish'
                    sh 'composer install --no-interaction --prefer-dist --no-progress --no-scripts'
                    sh 'test -f .env || cp .env.example .env'
                    sh 'php artisan package:discover --ansi'
                    sh 'mkdir -p ../artifacts'
                    catchError(buildResult: 'FAILURE', stageResult: 'FAILURE') {
                        sh 'php artisan test --log-junit ../artifacts/backend-tests.xml'
                    }
                }
            }
            post {
                always { junit testResults: 'artifacts/backend-tests.xml', allowEmptyResults: true }
            }
        }
        stage('Frontend web: tipos, lint y compilación') {
            steps {
                dir('frontend/apps/admin-web') {
                    sh '../../../ci/pnpm install --frozen-lockfile'
                    sh '../../../ci/pnpm exec vue-tsc --noEmit --project tsconfig.web.json'
                    sh '../../../ci/pnpm exec eslint -c eslint.config.js "./src/**/*.{ts,js,cjs,mjs,vue}"'
                    withEnv(['CI_WEB_ONLY=true', 'QCLI_PUBLIC_PATH=/admin/']) {
                        sh '../../../ci/pnpm run build'
                    }
                }
            }
        }
        stage('Paquete cPanel') {
            when { expression { currentBuild.currentResult == 'SUCCESS' } }
            steps {
                sh 'bash ci/cpanel/package'
                archiveArtifacts artifacts: 'artifacts/creceimpulso-cpanel.zip,artifacts/creceimpulso-cpanel.sha256', fingerprint: true
            }
        }
        stage('Publicar en cPanel de pruebas') {
            when { expression { (params.DEPLOY_CPANEL || env.CI_GIT_BRANCH == 'origin/main' || env.CI_GIT_BRANCH == 'main') && currentBuild.currentResult == 'SUCCESS' } }
            steps {
                withCredentials([usernamePassword(credentialsId: 'creceimpulso-pruebas-ftps', usernameVariable: 'CPANEL_FTP_USER', passwordVariable: 'CPANEL_FTP_PASSWORD')]) {
                    sh 'php ci/cpanel/upload.php'
                }
            }
        }
        stage('Artefactos web') {
            steps {
                sh 'bash ci/package-web'
                archiveArtifacts artifacts: 'artifacts/admin-web-web.tar.gz,artifacts/SHA256SUMS', fingerprint: true
            }
        }
    }
}
