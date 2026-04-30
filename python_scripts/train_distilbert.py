#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Обучение DistilBERT для классификации намерений
"""

import json
import torch
import numpy as np
import pandas as pd
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, classification_report
from transformers import (
    DistilBertTokenizer,
    DistilBertForSequenceClassification,
    Trainer,
    TrainingArguments,
    EarlyStoppingCallback
)
from torch.utils.data import Dataset
from typing import List, Dict
import os

# Настройки
MODEL_NAME = "distilbert-base-multilingual-cased"
DATA_FILE = "intents_training_data.json"
MODEL_SAVE_PATH = "models/intent_classifier"
BATCH_SIZE = 16
EPOCHS = 10
LEARNING_RATE = 2e-5

class IntentDataset(Dataset):
    """Датасет для классификации намерений"""

    def __init__(self, texts: List[str], labels: List[int], tokenizer, max_length: int = 128):
        self.texts = texts
        self.labels = labels
        self.tokenizer = tokenizer
        self.max_length = max_length

    def __len__(self):
        return len(self.texts)

    def __getitem__(self, idx):
        text = str(self.texts[idx])
        label = self.labels[idx]

        encoding = self.tokenizer(
            text,
            truncation=True,
            padding='max_length',
            max_length=self.max_length,
            return_tensors='pt'
        )

        return {
            'input_ids': encoding['input_ids'].flatten(),
            'attention_mask': encoding['attention_mask'].flatten(),
            'labels': torch.tensor(label, dtype=torch.long)
        }

def load_training_data(file_path: str):
    """Загружает обучающие данные"""
    with open(file_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    # Преобразуем в DataFrame
    df = pd.DataFrame(data)

    # Создаем mapping интентов к ID
    intents = df['intent'].unique()
    intent_to_id = {intent: i for i, intent in enumerate(intents)}
    id_to_intent = {i: intent for intent, i in intent_to_id.items()}

    # Преобразуем интенты в ID
    df['label'] = df['intent'].map(intent_to_id)

    print(f"Загружено {len(df)} примеров")
    print(f"Количество классов: {len(intents)}")
    print(f"Классы: {list(intents)}")

    return df, intent_to_id, id_to_intent

def prepare_datasets(df, tokenizer, test_size=0.2, val_size=0.1):
    """Подготавливает обучающую, валидационную и тестовую выборки"""

    # Сначала разделим на train+val и test
    train_val_df, test_df = train_test_split(
        df, test_size=test_size, random_state=42, stratify=df['label']
    )

    # Затем разделим train+val на train и val
    train_df, val_df = train_test_split(
        train_val_df, test_size=val_size/(1-test_size), random_state=42, stratify=train_val_df['label']
    )

    print(f"Размеры выборок:")
    print(f"  Обучающая: {len(train_df)}")
    print(f"  Валидационная: {len(val_df)}")
    print(f"  Тестовая: {len(test_df)}")

    # Создаем датасеты
    train_dataset = IntentDataset(
        texts=train_df['text'].tolist(),
        labels=train_df['label'].tolist(),
        tokenizer=tokenizer
    )

    val_dataset = IntentDataset(
        texts=val_df['text'].tolist(),
        labels=val_df['label'].tolist(),
        tokenizer=tokenizer
    )

    test_dataset = IntentDataset(
        texts=test_df['text'].tolist(),
        labels=test_df['label'].tolist(),
        tokenizer=tokenizer
    )

    return train_dataset, val_dataset, test_dataset

def compute_metrics(p):
    """Вычисляет метрики для оценки"""
    predictions, labels = p
    predictions = np.argmax(predictions, axis=1)

    accuracy = accuracy_score(labels, predictions)

    return {
        'accuracy': accuracy,
    }

def train_model():
    """Обучает модель DistilBERT"""
    print("Начинаем обучение модели DistilBERT...")

    # Создаем директорию для модели
    os.makedirs(MODEL_SAVE_PATH, exist_ok=True)

    # Загружаем токенизатор
    print("Загружаем токенизатор...")
    tokenizer = DistilBertTokenizer.from_pretrained(MODEL_NAME)

    # Загружаем данные
    print("Загружаем обучающие данные...")
    df, intent_to_id, id_to_intent = load_training_data(DATA_FILE)

    # Сохраняем mapping интентов
    with open(f"{MODEL_SAVE_PATH}/intent_mapping.json", 'w', encoding='utf-8') as f:
        json.dump({
            'intent_to_id': intent_to_id,
            'id_to_intent': id_to_intent
        }, f, ensure_ascii=False, indent=2)

    # Подготавливаем датасеты
    print("Подготавливаем датасеты...")
    train_dataset, val_dataset, test_dataset = prepare_datasets(df, tokenizer)

    # Загружаем модель
    print("Загружаем модель DistilBERT...")
    model = DistilBertForSequenceClassification.from_pretrained(
        MODEL_NAME,
        num_labels=len(intent_to_id),
        id2label=id_to_intent,
        label2id=intent_to_id
    )

    # Настройки обучения
    training_args = TrainingArguments(
        output_dir=MODEL_SAVE_PATH,
        num_train_epochs=EPOCHS,
        per_device_train_batch_size=BATCH_SIZE,
        per_device_eval_batch_size=BATCH_SIZE,
        warmup_steps=100,
        weight_decay=0.01,
        logging_dir=f"{MODEL_SAVE_PATH}/logs",
        logging_steps=10,
        eval_strategy="epoch",
        save_strategy="epoch",
        save_total_limit=3,
        load_best_model_at_end=True,
        metric_for_best_model="accuracy",
        greater_is_better=True,
        fp16=False,
        push_to_hub=False,
        report_to="none",
        gradient_accumulation_steps=1,
        gradient_checkpointing=False,
        dataloader_num_workers=0,
        remove_unused_columns=True,
    )

    # Создаем Trainer
    trainer = Trainer(
        model=model,
        args=training_args,
        train_dataset=train_dataset,
        eval_dataset=val_dataset,
        compute_metrics=compute_metrics,
        callbacks=[EarlyStoppingCallback(early_stopping_patience=3)]
    )

    # Обучаем модель
    print("Начинаем обучение...")
    trainer.train()

    # Сохраняем модель и токенизатор
    print("Сохраняем модель...")
    model.save_pretrained(MODEL_SAVE_PATH)
    tokenizer.save_pretrained(MODEL_SAVE_PATH)

    # Оцениваем на тестовой выборке
    print("Оцениваем на тестовой выборке...")
    test_results = trainer.evaluate(test_dataset)
    print(f"Точность на тестовой выборке: {test_results['eval_accuracy']:.4f}")

    # Делаем предсказания на тестовой выборке
    print("\nДемонстрация работы модели:")
    test_texts = df['text'].sample(min(5, len(df)), random_state=42).tolist()
    test_labels = df.loc[df['text'].isin(test_texts), 'label'].tolist()

    for i, text in enumerate(test_texts):
        inputs = tokenizer(text, return_tensors="pt", truncation=True, padding=True, max_length=128)

        with torch.no_grad():
            outputs = model(**inputs)
            predictions = torch.softmax(outputs.logits, dim=-1)
            predicted_id = torch.argmax(predictions, dim=-1).item()
            confidence = predictions[0][predicted_id].item()

        predicted_intent = id_to_intent[predicted_id]
        actual_intent = id_to_intent[int(test_labels[i])]

        print(f"Текст: '{text[:50]}...'")
        print(f"  Предсказано: {predicted_intent} ({confidence:.2%})")
        print(f"  Фактически: {actual_intent}")
        print(f"  Верно: {'✓' if predicted_intent == actual_intent else '✗'}")
        print()

    print(f"Модель успешно обучена и сохранена в {MODEL_SAVE_PATH}")
    print("Для использования модели в чате, обновите neural_chat_model.py")

if __name__ == "__main__":
    # Проверяем наличие GPU
    print(f"PyTorch версия: {torch.__version__}")
    print(f"CUDA доступен: {torch.cuda.is_available()}")
    if torch.cuda.is_available():
        print(f"GPU: {torch.cuda.get_device_name(0)}")

    # Запускаем обучение
    train_model()
