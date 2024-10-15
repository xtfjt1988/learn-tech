
                            
                            因收到Google相关通知，网站将会择期关闭。相关通知内容
                            
                            
                            19 资料：Awesome Elasticsearch
                            General

Elastic Stack


Elasticsearch official website
Logstash is a data pipeline that helps you process logs and other event data from a variety of systems
Kibana is a data analysis tool that helps to visualize your data; Kibana Manual docs
beats is the platform for building lightweight, open source data shippers for many types of data you want to enrich with Logstash, search and analyze in Elasticsearch, and visualize in Kibana.


Books


Deep Learning for Search - teaches you how to leverage neural networks, NLP, and deep learning techniques to improve search performance. (2019)
Relevant Search: with applications for Solr and Elasticsearch - demystifies relevance work. Using Elasticsearch, it teaches you how to return engaging search results to your users, helping you understand and leverage the internals of Lucene-based search engines. (2016)
Elasticsearch in Action - teaches you how to build scalable search applications using Elasticsearch (2015)


Related (awesome) lists


frutik/awesome-search I am building e-commerce search now. Below are listed some of my build blocks


Open-source and free products, based on Elasticsearch


Fess is an open source full featured Enterprise Search, with a web-crawler
Yelp/elastalert is a modular flexible rules based alerting system written in Python
etsy/411 - an Alert Management Web Application https://demo.fouroneone.io (credentials: user/user)
appbaseio/mirage is a 🔎 GUI for composing Elasticsearch queries
exceptionless/Exceptionless is an error (exceptions) collecting and reporting server with client bindings for a various programming languages
searchkit/searchkit is a UI framework based on React to build awesome search experiences with Elasticsearch
appbaseio/reactivemaps is a React based UI components library for building Airbnb / Foursquare like Maps
appbaseio/reactivesearch is a library of beautiful React UI components for Elasticsearch
appbaseio/dejavu The missing UI for Elasticsearch; landing page
Simple File Server is an Openstack Swift compatible distributed object store that can serve and securely store billions of large and small files using minimal resources.
logagent a log shipper to parse and ship logs to Elasticsearch including bulk indexing, disk buffers and log format detection.
ItemsAPI simplified search API for web and mobile (based on Elasticsearch and Express.js)
Kuzzle - An open-source backend with advanced real-time features for Web, Mobile and IoT that uses ElasticSearch as a database. (Website )
SIAC - SIAC is an enterprise SIEM built on the ELK stack and other open-source components.
Sentinl - Sentinl is a Kibana alerting and reporting app.
Praeco - Elasticsearch alerting made simple


Elasticsearch developer tools and utilities

Development and debugging


Sense (from Elastic) A JSON aware developer console to Elasticsearch; official and very powerful
ES-mode An Emacs major mode for interacting with Elasticsearch (similar to Sense)
Elasticsearch Cheatsheet Examples for the most used queries, API and settings for all major version of Elasticsearch
Elasticstat CLI tool displaying monitoring informations like htop
Elastic for Visual Studio Code An extension for developing Elasticsearch queries like Kibana and Sense extention in Visual Studio Code
Elastic Builder A Node.js implementation of the Elasticsearch DSL
Bodybuilder A Node.js elasticsearch query body builder
enju A Node.js elasticsearch ORM
Peek An interactive CLI in Python that works like Kibana Console with additional features


Import and Export


Knapsack plugin is an “swiss knife” export/import plugin for Elasticsearch
Elasticsearch-Exporter is a command line script to import/export data from Elasticsearch to various other storage systems
esbulk Parallel elasticsearch bulk indexing utility for the command line.
elasticdump - tools for moving and saving indices
elasticsearch-loader - Tool for loading common file types to elasticsearch including csv, json, and parquet


Management


Esctl - High-level command line interface to manage Elasticsearch clusters.
Vulcanizer - Github’s open sourced cluster management library based on Elasticsearch’s REST API. Comes with a high level CLI tool


Elasticsearch plugins

Cluster


sscarduzio/elasticsearch-readonlyrest-plugin Safely expose Elasticsearch REST API directly to the public
mobz/elasticsearch-head is a powerful and essential plugin for managing your cluster, indices and mapping
Bigdesk - Live charts and statistics for elasticsearch cluster
Elastic HQ - Elasticsearch cluster management console with live monitoring and beautiful UI
Cerebro is an open source(MIT License) elasticsearch web admin tool. Supports ES 5.x
Kopf - Another management plugin that have REST console and manual shard allocation
Search Guard - Elasticsearch and elastic stack security and alerting for free
ee-outliers - ee-outliers is a framework to detect outliers in events stored in an Elasticsearch cluster.
Elasticsearch Comrade - Elasticsearch admin panel built for ops and monitoring
elasticsearch-admin - Web administration for Elasticsearch


Other


SIREn Join Plugin for Elasticsearch This plugin extends Elasticsearch with new search actions and a filter query parser that enables to perform a “Filter Join” between two set of documents (in the same index or in different indexes).


Integrations and SQL support


NLPchina/elasticsearch-sql - Query elasticsearch using familiar SQL syntax. You can also use ES functions in SQL.
elastic/elasticsearch-hadoop - Elasticsearch real-time search and analytics natively integrated with Hadoop (and Hive)
jprante/elasticsearch-jdbc - JDBC importer for Elasticsearch
pandasticsearch - An Elasticsearch client exposing DataFrame API
monstache - Go daemon that syncs MongoDB to Elasticsearch in near realtime


You know, for search


jprante/elasticsearch-plugin-bundle A plugin that consists of a compilation of useful Elasticsearch plugins related to indexing and searching documents


Kibana plugins and applications


elastic/timelion time-series analyses application. Overview and installation guide: Timelion: The time series composer for Kibana
Kibana Alert App for Elasticsearch - Kibana plugin with monitoring, alerting and reporting capabilities
VulnWhisperer - VulnWhisperer is a vulnerability data and report aggregator.
Wazuh Kibana App - A Kibana app for working with data generated by Wazuh .
Datasweet Formula - A real time calculated metric plugin Datasweet Formula .


Kibana Visualization plugins


nbs-system/mapster - a visualization which allows to create live event 3d maps in Kibana
Kibana Tag Cloud Plugin - tag cloud visualization plugin based on d3-cloud plugin
LogTrail - a plugin for Kibana to view, analyze, search and tail log events from multiple hosts in realtime with devops friendly interface inspired by Papertrail
Analyze API - Kibana 6 application to manipulate the _analyze API graphically
kbn_network - This is a plugin developed for Kibana that displays a network node that link two fields that have been previously selected.


Discussions and social media


/r/elasticsearch
Elasticsearch forum
Stackoverflow
Books on Amazon does not fit well into this category, but worth checking out!
TODO: Put some good twitter accounts


Tutorials


Centralized Logging with Logstash and Kibana On Ubuntu 14.04 everything you need to now when you are creating your first Elasticsearch+Logstash+Kibana instance
dwyl/learn-elasticsearch a getting started tutorial with a pack of valuable references
Make Sense of your Logs: From Zero to Hero in less than an Hour! by Britta Weber demonstrates how you can build Elasticsearch + Logstash + Kibana stack to collect and discover your data
$$ Elasticsearch 7 and Elastic Stack - liveVideo course that teaches you to search, analyze, and visualize big data on a cluster with Elasticsearch, Logstash, Beats, Kibana, and more.


Articles

System configuration


A Useful Elasticsearch Cheat Sheet in Times of Trouble
The definitive guide for Elasticsearch on Windows Azure
Elasticsearch pre-flight checklist
9 Tips on Elasticsearch Configuration for High Performance
Best Practices in AWS
How to Secure Elasticsearch and Kibana with NGINX, LDAP and SSL 🔒
Elasticsearch server on Webfaction using NGINX with basic authorization and HTTPS protocol
Elasticsearch Guides Useful Elasticsearch guides with best practices, troubleshooting instructions for errors, tips, examples of code snippets and more.


Docker and Elasticsearch


Running an Elasticsearch cluster with Docker


Java tuning


Elasticsearch Java Virtual Machine settings explained
Tuning Garbage Collection for Mission-Critical Java Applications
G1: One Garbage Collector To Rule Them All
Use Lucene’s MMapDirectory on 64bit platforms, please!
Black Magic cookbook
G1GC Fundamentals: Lessons from Taming Garbage Collection
JVM Garbage Collector settings investigation PDF Comparison of JVM GC
Garbage Collection Settings for Elasticsearch Master Nodes Fine tunine your garbage collector
Understanding G1 GC Log Format To tune and troubleshoot G1 GC enabled JVMs, one must have a proper understanding of G1 GC log format. This article walks through key things that one should know about the G1 GC log format.


How to start using G1

#ES_JAVA_OPTS=""
ES_JAVA_OPTS="-XX:-UseParNewGC -XX:-UseConcMarkSweepGC -XX:+UseG1GC"


Scalable Infrastructure and performance


The Authoritative Guide to Elasticsearch Performance Tuning (Part 1) Part 2 Part 3
Tuning data ingestion performance for Elasticsearch on Azure - and not only for Azure. That’s a great article about Elasticsearch Performance testing by example
Elasticsearch Indexing Performance Cheatsheet - when you plan to index large amounts of data in Elasticsearch (by Patrick Peschlow)
Elasticsearch for Logging Elasticsearch configuration tips and tricks from Sanity
Scaling Elasticsearch to Hundreds of Developers by Joseph Lynch @yelp
10 Elasticsearch metrics to watch
Understanding Elasticsearch Performance
Our Experience of Creating Large Scale Log Search System Using Elasticsearch - topology, separate master, data and search balancers nodes
📂 Elasticsearch on Azure Guidance it is 10% on Azure and 90% of a very valuable general information, tips and tricks about Elasticsearch
How to avoid the split-brain problem in Elasticsearch
Datadog’s series about monitoring Elasticsearch performance:


How to monitor Elasticsearch performance
How to collect Elasticsearch metrics
How to monitor Elasticsearch with Datadog
How to solve 5 Elasticsearch performance and scaling problems

Performance Monitoring Essentials - Elasticsearch Edition
Operator for running Elasticsearch in Kubernetes


Integrations


Apache Hive integration
Connecting Tableau to Elasticsearch (READ: How to query Elasticsearch with Hive SQL and Hadoop)
mradamlacey/elasticsearch-tableau-connector


Logging


5 Logstash Alternatives and typical use cases


Alerts


ElastAlert: Alerting At Scale With Elasticsearch, Part 1 by engineeringblog.yelp.com
ElastAlert: Alerting At Scale With Elasticsearch, Part 2 by engineeringblog.yelp.com
Elastalert: implementing rich monitoring with Elasticsearch


Time series


Elasticsearch as a Time Series Data Store by Felix Barnsteiner
Running derivatives on Voyager velocity data By Colin Goodheart-Smithe
Shewhart Control Charts via Moving Averages: Part 1 - Part 2 by Zachary Tong
Implementing a Statistical Anomaly Detector: Part 1 - Part 2 - Part 3 by Zachary Tong


Machine Learning


Classifying images into Elasticsearch with DeepDetect (forum thread with discussion ) by Emmanuel Benazera
Elasticsearch with Machine Learning (English translation ) by Kunihiko Kido
Recommender System with Mahout and Elasticsearch


Use cases for Elasticsearch


Data Infrastructure at IFTTT Elasticsearch, Kafka, Apache Spark, Redhsift, other AWS services
OFAC compliance with Elasticsearch using AWS
Building a Streaming Search Platform - Streaming Search on Tweets: Storm, Elasticsearch, and Redis


Other


LogZoom, a fast and lightweight substitute for Logstash
Graylog2/graylog2-server - Free and open source log management (based on ES)
Fluentd vs. Logstash for OpenStack Log Management
Building a Directory Map With ELK
Structured logging with ELK - part 1
Search for 😋 Emoji with Elasticsearch 🔎
Complete Guide to the ELK Stack
logiq - Simple WebUI Monitoring Tool for Logstash ver. 5.0 and up
ElasticSearch Report Engine - An ElasticSearch plugin to return query results as either PDF,HTML or CSV.
Elasticsearch Glossary - explanations of Elasticsearch terminology, including examples, common best practices and troubleshooting guides for various issues.


Videos

Overviews


Elasticsearch for logs and metrics: A deep dive – Velocity 2016 by Sematext Developers
Elasticsearch in action Thijs Feryn a beginner overview
Getting Down and Dirty with ElasticSearch by Clinton Gormley 
How we scaled Raygun
Getting started with Elasticsearch
Speed is a Key: Elasticsearch under the Hood introduction + basic performance optimization
$$ Pluralsight: Getting Started With Elasticsearch for .NET Developers this course will introduce users to Elasticsearch, how it works, and how to use it with .NET projects.
$$ Complete Guide to Elasticsearch Comprehensive guide to Elasticsearch, the popular search engine built on Apache Lucene
How Elasticsearch powers the Guardian’s newsroom
Elasticsearch Query Editor in Grafana
Scale Your Metrics with Elasticsearch 2019 by Philipp Krenn (Elastic) optimization tips and tricks


Advanced


#bbuzz 2015: Adrien Grand – Algorithms and data-structures that power Lucene and Elasticsearch
Rafał Kuć - Running High Performance Fault-tolerant Elasticsearch Clusters on Docker and slides
Working with Elasticsearch - Search, Aggregate, Analyze, and Scale Large Volume Datastores - O’Reilly Media
End-to-end Recommender System with Spark and Elasticsearch by Nick Pentreath & Jean-François Puget. Slide deck


Code, configuration file samples and other gists


Elasticsearch config for a write-heavy cluster - reyjrar/elasticsearch.yml
chenryn/ESPL - Elastic Search Processing Language PEG parser sample for SPL to Elasticsearch DSL
thomaspatzke/EQUEL an Elasticsearch QUEry Language, based on G4 grammar parser


Who is using elasticsearch?

Yelp , IFTTT , StackExchange , Raygun , Mozilla , Spotify , CERN , NASA Zalando

I want more! (Elasticsearch related resources)


Technology Explained Blog
EagerElk
Tim Roes Blog


Contributing


Make sure you are about to post a valuable resource that belongs to this list
Do NOT group ++Add and –Remove changes in same PR. Make them separate pull requests
Use spellchecker
All spelling and grammar corrections are welcome (except for the rule above)
Fork this repo, do your edits, send the pull request
Feel free to create any new sections
Do not even try to add this repo to any awesome-awesome-* lists


                        
                        
                            